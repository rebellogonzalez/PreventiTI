#!/usr/bin/env python3
"""Cambio local de conexión con mantenimiento breve y conservación de cada .env."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

p = argparse.ArgumentParser()
p.add_argument('--home', required=True, type=Path)
p.add_argument('--to', required=True, choices=['mariadb', 'sqlite'])
args = p.parse_args()
project = Path(__file__).resolve().parents[2]
home = args.home.resolve(strict=True)
env_path = project / '.env'
original = env_path.read_text()
baseline = (home / 'respaldo/env.sqlite.original').read_text()

def values(text):
    return {line.split('=', 1)[0]: line.split('=', 1)[1] for line in text.splitlines() if '=' in line and not line.startswith('#')}

assert values(original).get('APP_KEY') == values(baseline).get('APP_KEY'), 'La clave de la aplicación cambió; revisar la reversión.'
keys = ['DB_CONNECTION', 'DB_URL', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET', 'MARIADB_DUMP_BINARY', 'MARIADB_BACKUP_DEFAULTS_FILE', 'PREVENTITI_BACKUP_DIRECTORY']
if args.to == 'mariadb':
    mapping = json.loads((home / 'privado/export-inicial/mapping.json').read_text())
    before = json.loads((home / 'evidencia/antes.json').read_text())
    assert Path(before['project']).resolve() == project, 'El respaldo pertenece a otro proyecto.'
    # sqlite3.backup conserva filas, pero puede cambiar bytes de cabecera: el
    # archivo vivo se contrasta con su propio hash previo, no con el del backup.
    assert hashlib.sha256((project / 'database/database.sqlite').read_bytes()).hexdigest() == before['sqlite_sha256'], 'SQLite cambió desde la exportación: repetir la migración antes de conectar.'
    assert hashlib.sha256((home / 'respaldo/database.sqlite').read_bytes()).hexdigest() == mapping['source_sha256'], 'La instantánea exportada cambió.'
    runtime = home / 'runtime'
    credentials = json.loads((home / 'privado/credentials.json').read_text())
    sys.path.insert(0, str(next((runtime / 'paquetes').glob('pymysql-*.whl'))))
    import pymysql
    connection = pymysql.connect(unix_socket=str(runtime / 'mariadb.sock'), user='root', password=credentials['root'], database='preventiti')
    with connection.cursor() as cursor:
        cursor.execute('SELECT VERSION(),@@datadir,@@skip_networking')
        version, directory, no_tcp = cursor.fetchone()
        assert version.startswith('10.11.') and Path(directory).resolve() == runtime / 'datos' and no_tcp
        cursor.execute('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')
        assert cursor.fetchone()[0] == 10, 'Faltan reglas SQL.'
        cursor.execute('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')
        assert cursor.fetchone()[0] == 3, 'Faltan rutinas SQL.'
    connection.close()
    updates = dict(DB_CONNECTION='mariadb', DB_URL='', DB_HOST='localhost', DB_PORT='3306', DB_DATABASE='preventiti', DB_USERNAME='preventiti_app', DB_PASSWORD=credentials['app'], DB_SOCKET=str(runtime / 'mariadb.sock'), MARIADB_DUMP_BINARY=str(runtime / 'opt/mariadb@10.11/bin/mariadb-dump'), MARIADB_BACKUP_DEFAULTS_FILE=str(home / 'privado/backup.cnf'), PREVENTITI_BACKUP_DIRECTORY=str(home / 'backups'))
    updates = {key: '"' + value + '"' for key, value in updates.items()}
else:
    # Solo cambia las claves de conexión; no reemplaza otras preferencias posteriores.
    updates = {key: value for key, value in values(baseline).items() if key in keys}

lines = [line for line in original.splitlines() if line.split('=', 1)[0] not in keys]
replacement = '\n'.join(lines).rstrip() + '\n\n' + '\n'.join(key + '=' + value for key, value in updates.items()) + '\n'
stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
backup = home / 'privado' / ('env.antes-' + stamp)
backup.write_text(original)
backup.chmod(0o600)

def atomic_write(text):
    fd, path = tempfile.mkstemp(prefix='.env.connection-', dir=project)
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write(text)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(path, env_path)
    finally:
        if os.path.exists(path): os.unlink(path)

subprocess.run(['php', 'artisan', 'down', '--retry=15'], cwd=project, check=True)
try:
    atomic_write(replacement)
    subprocess.run(['php', 'artisan', 'config:clear'], cwd=project, check=True)
    # Comprueba la conexión de Laravel antes de levantar la aplicación.
    subprocess.run(['php', 'artisan', 'migrate:status', '--no-interaction'], cwd=project, check=True)
except BaseException:
    atomic_write(original)
    subprocess.run(['php', 'artisan', 'config:clear'], cwd=project, check=True)
    raise
finally:
    subprocess.run(['php', 'artisan', 'up'], cwd=project, check=True)
print('Conexión activa: ' + args.to + '. No se sincronizaron ni modificaron los datos del otro motor.')
