#!/usr/bin/env python3
"""Servidor local privado. Nunca usa credenciales ni servidores ajenos al --home."""
import argparse
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import time

parser=argparse.ArgumentParser()
parser.add_argument('--home',required=True,type=Path)
parser.add_argument('action',choices=['init','start','stop','status','run'])
parser.add_argument('--role',choices=['app','test','admin'],default='app')
parser.add_argument('--database',choices=['preventiti','preventiti_tests','preventiti_restore_tests'])
parser.add_argument('command',nargs=argparse.REMAINDER)
args=parser.parse_args()
HOME=args.home.resolve(strict=True)
RUNTIME=HOME/'runtime';PREFIX=RUNTIME/'opt/mariadb@10.11';SOCKET=RUNTIME/'mariadb.sock'
sys.path.insert(0,str(next((RUNTIME/'paquetes').glob('pymysql-*.whl'))))
import pymysql

def private_json(path,data):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_TRUNC,0o600)
    with os.fdopen(fd,'w') as stream:json.dump(data,stream,indent=2);stream.write('\n')
    path.chmod(0o600)

def cnf(path,user,password):
    path.write_text('[client]\nuser='+user+'\npassword='+password+'\nprotocol=SOCKET\nsocket='+str(SOCKET)+'\ndefault-character-set=utf8mb4\n')
    path.chmod(0o600)

def connect(initial=False):
    password='' if initial else json.loads((HOME/'privado/credentials.json').read_text())['root']
    db=pymysql.connect(unix_socket=str(SOCKET),user='root',password=password,charset='utf8mb4',autocommit=True)
    with db.cursor() as cursor:
        cursor.execute('SELECT VERSION(),@@datadir,@@skip_networking')
        version,path,no_tcp=cursor.fetchone()
        if not version.startswith('10.11.') or Path(path).resolve()!=(RUNTIME/'datos').resolve() or not no_tcp:
            db.close();raise RuntimeError('El servidor no corresponde al directorio MariaDB privado solicitado.')
    return db

def start(initial=False):
    try:
        conn=connect(initial);conn.close();return
    except pymysql.OperationalError:pass
    if not (RUNTIME/'datos').exists():
        subprocess.run([str(PREFIX/'bin/mariadb-install-db'),'--no-defaults','--basedir='+str(PREFIX),'--datadir='+str(RUNTIME/'datos'),'--auth-root-authentication-method=normal','--skip-test-db'],check=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
    options=[str(PREFIX/'bin/mariadbd'),'--no-defaults','--basedir='+str(PREFIX),'--datadir='+str(RUNTIME/'datos'),'--plugin-dir='+str(PREFIX/'lib/plugin'),'--lc-messages-dir='+str(PREFIX/'share/mysql'),'--socket='+str(SOCKET),'--pid-file='+str(RUNTIME/'mariadb.pid'),'--log-error='+str(RUNTIME/'mariadb.log'),'--skip-networking','--skip-name-resolve','--default-time-zone=+00:00']
    with (RUNTIME/'arranque.log').open('ab') as output:process=subprocess.Popen(options,cwd=RUNTIME,stdout=output,stderr=output,start_new_session=True)
    for _ in range(100):
        if process.poll() is not None:raise RuntimeError('Fallo al iniciar MariaDB; consulte el log privado.')
        try:conn=connect(initial);conn.close();return
        except pymysql.OperationalError:time.sleep(.1)
    raise RuntimeError('MariaDB no respondió en diez segundos.')

if args.action=='init':
    if (HOME/'privado/credentials.json').exists():raise SystemExit('Ya está inicializado; no se renuevan credenciales existentes.')
    (HOME/'privado').mkdir(mode=0o700,exist_ok=True)
    start(True);conn=connect(True)
    credentials={key:secrets.token_hex(32) for key in ['root','app','test','backup']}
    with conn.cursor() as cursor:
        for role in ['app','test','backup']:
            cursor.execute('CREATE USER %s@localhost IDENTIFIED BY %s',('preventiti_'+role,credentials[role]))
        cursor.execute('GRANT SELECT,INSERT,UPDATE,DELETE,EXECUTE ON preventiti.* TO preventiti_app@localhost')
        cursor.execute('CREATE DATABASE preventiti_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        cursor.execute('CREATE DATABASE preventiti_restore_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        for database in ['preventiti_tests','preventiti_restore_tests']:
            cursor.execute('GRANT ALL PRIVILEGES ON '+database+'.* TO preventiti_test@localhost')
        for database in ['preventiti','preventiti_tests','preventiti_restore_tests']:
            cursor.execute('GRANT SELECT,SHOW VIEW,TRIGGER,EVENT ON '+database+'.* TO preventiti_backup@localhost')
        cursor.execute('GRANT SELECT ON mysql.proc TO preventiti_backup@localhost')
        cursor.execute("SELECT Host FROM mysql.user WHERE User='root'")
        for (host,) in cursor.fetchall():cursor.execute('ALTER USER root@%s IDENTIFIED BY %s',(host,credentials['root']))
    conn.close();private_json(HOME/'privado/credentials.json',credentials)
    cnf(HOME/'privado/admin.cnf','root',credentials['root'])
    cnf(HOME/'privado/backup.cnf','preventiti_backup',credentials['backup'])
    cnf(HOME/'privado/test.cnf','preventiti_test',credentials['test'])
    print('MariaDB 10.11 privado inicializado, sin TCP; credenciales nuevas guardadas con permiso 0600.')
elif args.action=='start':start();print('MariaDB iniciado.')
elif args.action=='stop':
    conn=connect();conn.close()
    subprocess.run([str(PREFIX/'bin/mariadb-admin'),'--defaults-file='+str(HOME/'privado/admin.cnf'),'shutdown'],check=True)
    print('MariaDB detenido.')
elif args.action=='status':
    conn=connect()
    with conn.cursor() as cursor:
        cursor.execute('SELECT VERSION(),@@datadir,@@skip_networking');print(json.dumps(cursor.fetchone()))
    conn.close()
elif args.action=='run':
    credentials=json.loads((HOME/'privado/credentials.json').read_text())
    database=args.database or ('preventiti_tests' if args.role=='test' else 'preventiti')
    if args.role=='test' and database=='preventiti':raise SystemExit('Las pruebas no pueden usar la base operativa.')
    command=args.command[1:] if args.command[:1]==['--'] else args.command
    if not command:raise SystemExit('Falta el comando.')
    env=os.environ.copy()
    env.update({'DB_CONNECTION':'mariadb','DB_URL':'','DB_HOST':'localhost','DB_SOCKET':str(SOCKET),'DB_DATABASE':database,'DB_USERNAME':'root' if args.role=='admin' else 'preventiti_'+args.role,'DB_PASSWORD':credentials['root' if args.role=='admin' else args.role],
        'MARIADB_DUMP_BINARY':str(PREFIX/'bin/mariadb-dump'),'MARIADB_BACKUP_DEFAULTS_FILE':str(HOME/'privado/backup.cnf'),
        'MARIADB_CLIENT_BINARY':str(PREFIX/'bin/mariadb'),'MARIADB_TEST_DEFAULTS_FILE':str(HOME/'privado/test.cnf'),
        'PREVENTITI_BACKUP_DIRECTORY':str(HOME/'backups')})
    if args.role=='test':env.update({'APP_ENV':'testing','CACHE_STORE':'array','SESSION_DRIVER':'array','QUEUE_CONNECTION':'sync'})
    raise SystemExit(subprocess.run(command,env=env).returncode)
