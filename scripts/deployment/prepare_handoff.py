#!/usr/bin/env python3
"""Entrega privada reproducible del commit, datos actuales y APP_KEY. No publica archivos."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import zipfile

p = argparse.ArgumentParser()
p.add_argument('--out', required=True, type=Path)
p.add_argument('--mariadb-home', required=True, type=Path)
args = p.parse_args()
project = Path(__file__).resolve().parents[2]
destination = args.out.resolve()
server_home = args.mariadb_home.resolve(strict=True)
os.umask(0o077)
if destination.is_relative_to(project): raise SystemExit('La entrega privada debe quedar fuera del repositorio.')
if destination.exists(): raise SystemExit('El destino debe ser nuevo; no se sobrescriben entregas.')

def run(command, **kwargs):
    result = subprocess.run(command, cwd=project, capture_output=True, **kwargs)
    if result.returncode:
        if destination.is_dir():
            with (destination / 'ERROR_PREPARACION.log').open('ab') as log:
                log.write(result.stdout+result.stderr)
        raise RuntimeError('Falló '+str(command[0])+'. No se declara lista la entrega.')
    return result.stdout

if run(['git', 'status', '--porcelain']).strip(): raise SystemExit('Primero crear el commit; la entrega exige un árbol Git limpio.')
if (project / 'bootstrap/cache/config.php').exists(): raise SystemExit('Limpiar la configuración cacheada antes de preparar la entrega.')
if (project / 'storage/framework/down').exists(): raise SystemExit('La aplicación ya estaba en mantenimiento; no se cambia su estado.')
sha = run(['git', 'rev-parse', 'HEAD']).decode().strip()
source_hash = hashlib.sha256((project / 'database/database.sqlite').read_bytes()).hexdigest()
key_line = next(line for line in (project / '.env').read_text().splitlines() if line.startswith('APP_KEY='))
if not key_line.partition('=')[2].strip(): raise SystemExit('Falta la clave de la aplicación.')
destination.mkdir(mode=0o700)
runner = [sys.executable, '-B', str(project / 'scripts/database/local_mariadb.py'), '--home', str(server_home)]
run(runner + ['status'])  # Comprueba versión, datadir y socket del servidor privado.
client = str(server_home / 'runtime/opt/mariadb@10.11/bin/mariadb')
client_options = ['--defaults-file='+str(server_home / 'privado/admin.cnf'), '--binary-mode', '--comments']
probe = 'preventiti_entrega_verificacion_'+secrets.token_hex(5)
created_probe = False
maintenance = False
try:
    run(['php', 'artisan', 'down', '--retry=15'])
    maintenance = True
    backup = json.loads(run(['php', 'scripts/database/backup.php']))
    if not backup['verified'] or backup['database'] != 'mariadb': raise RuntimeError('Se esperaba un respaldo MariaDB verificado.')
    manifest_data = run(runner + ['--role', 'admin', 'run', '--', 'php', 'scripts/deployment/database_manifest.php'])
    manifest = json.loads(manifest_data)
    with (destination / '01_codigo.tar.gz').open('wb') as output:
        subprocess.run(['git', 'archive', '--format=tar.gz', '--prefix=preventiti/', sha], cwd=project, stdout=output, check=True)
    shutil.copyfile(server_home / 'backups' / backup['filename'], destination / '02_respaldo.zip')
    (destination / '03_app-key.env').write_text(key_line+'\n')
    (destination / '04_estado_base.json').write_bytes(manifest_data)
    with zipfile.ZipFile(destination / '02_respaldo.zip') as archive:
        for entry in json.loads(archive.read('manifest.json'))['files']:
            data = archive.read(entry['path'])
            if len(data) != entry['bytes'] or hashlib.sha256(data).hexdigest() != entry['sha256']: raise RuntimeError('ZIP no coincide con su manifiesto.')
        sql = archive.read('database/database.sql')
    # Base aleatoria exclusiva de esta ejecución; no se reutiliza ninguna base existente.
    run([client, *client_options], input=('CREATE DATABASE `'+probe+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;').encode())
    created_probe = True
    run([client, *client_options, probe], input=sql)
    credentials = json.loads((server_home / 'privado/credentials.json').read_text())
    environment = os.environ.copy()
    environment.update(DB_CONNECTION='mariadb', DB_URL='', DB_HOST='localhost', DB_SOCKET=str(server_home / 'runtime/mariadb.sock'), DB_DATABASE=probe, DB_USERNAME='root', DB_PASSWORD=credentials['root'])
    restored = json.loads(run(['php', 'scripts/deployment/database_manifest.php', '--expected', str(destination / '04_estado_base.json')], env=environment))
    # Caso negativo: una alteración del clon debe invalidar la comparación.
    run([client, *client_options, probe], input=b"UPDATE auditorias SET descripcion=CONCAT(descripcion,' [prueba de alteracion]') ORDER BY id LIMIT 1;")
    negative = subprocess.run(['php', 'scripts/deployment/database_manifest.php', '--expected', str(destination / '04_estado_base.json')], cwd=project, env=environment, capture_output=True)
    if negative.returncode == 0 or b'auditorias' not in negative.stderr: raise RuntimeError('La verificación no detectó la alteración de prueba.')
    if hashlib.sha256((project / 'database/database.sqlite').read_bytes()).hexdigest() != source_hash: raise RuntimeError('Cambió SQLite durante la entrega.')
    (destination / '05_validacion.json').write_text(json.dumps({'commit': sha, 'created_at': datetime.now(timezone.utc).isoformat(), 'restored_backup': restored, 'tamper_rejected': True, 'zip_manifest_verified': True, 'original_sqlite_unchanged': True}, indent=2)+'\n')
    (destination / 'LEEME.txt').write_text(
        'ENTREGA PRIVADA PREVENTITI PARA NACHO\n\n'
        'Commit: '+sha+'\n'
        'Rama: '+run(['git', 'branch', '--show-current']).decode().strip()+'\n'
        'Repositorio: https://github.com/rebellogonzalez/PreventiTI\n\n'
        '01_codigo.tar.gz: código exacto del commit, sin dependencias ni .env.\n'
        '02_respaldo.zip: SQL MariaDB completo y storage/app.\n'
        '03_app-key.env: clave original; incorporar en el .env de Ubuntu.\n'
        '04_estado_base.json: huellas y conteos para verificar ANTES de abrir tráfico.\n'
        '05_validacion.json: restauración local verificada de este respaldo.\n'
        'SHA256SUMS: comprobar con sha256sum -c SHA256SUMS.\n\n'
        'Después de extraer el código, seguir ENTREGA_NACHO.md.\n'
        'Contiene datos reales y una clave secreta. Enviar por canal privado; no publicar.\n'
        'No contiene contraseñas del servidor MariaDB del Mac ni binarios macOS.\n'
        'Es una instantánea: el Mac vuelve a estar disponible al terminar. Coordinar el corte final para no perder cambios posteriores.\n')
    files = sorted(path for path in destination.iterdir() if path.is_file())
    (destination / 'SHA256SUMS').write_text(''.join(hashlib.sha256(path.read_bytes()).hexdigest()+'  '+path.name+'\n' for path in files))
    for path in destination.iterdir(): path.chmod(0o600)
finally:
    try:
        if created_probe: run([client, *client_options], input=('DROP DATABASE `'+probe+'`;').encode())
    finally:
        if maintenance: run(['php', 'artisan', 'up'])
print(json.dumps({'directory': str(destination), 'commit': sha, 'restored_backup': restored, 'private': True}, ensure_ascii=False))
