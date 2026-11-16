#!/usr/bin/env python3
"""Exporta esquema y datos privados sin cambiar SQLite; no incluye secretos en el código."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import os
import sqlite3

PROJECT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description='Exportación privada desde SQLite, sin modificar el origen.')
parser.add_argument('--source', required=True, type=Path)
parser.add_argument('--out', required=True, type=Path)
parser.add_argument('--database', required=True)
parser.add_argument('--allow-private-data', action='store_true')
args = parser.parse_args()
if not args.allow_private_data:
    raise SystemExit('Esta exportación conserva datos y hashes de cuentas; requiere --allow-private-data y un destino privado.')
if not re.fullmatch(r'[a-z][a-z0-9_]{0,63}', args.database):
    raise SystemExit('Nombre de base no válido.')
DATABASE = args.database
ROOT = args.out.resolve()
if ROOT.is_relative_to(PROJECT):
    raise SystemExit('Los volcados privados deben guardarse fuera del repositorio.')
ROOT.mkdir(mode=0o700, parents=True, exist_ok=False)
(ROOT/'sql').mkdir(mode=0o700)
source = args.source.resolve(strict=True)
if Path(str(source) + '-wal').exists():
    raise SystemExit('Hay WAL: se requiere una instantánea coherente autorizada; no se ignora.')
db = sqlite3.connect(source.as_uri() + '?mode=ro&immutable=1', uri=True)
db.row_factory = sqlite3.Row
assert db.execute('PRAGMA integrity_check').fetchall()[0][0] == 'ok'
assert not db.execute('PRAGMA foreign_key_check').fetchall()
tables = {row['name']:row['sql'] for row in db.execute("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")}
meta = {}
for name, ddl in tables.items():
    columns = [dict(row) for row in db.execute('PRAGMA table_info("' + name + '")')]
    foreign_keys = [dict(row) for row in db.execute('PRAGMA foreign_key_list("' + name + '")')]
    indexes = []
    for row in db.execute('PRAGMA index_list("' + name + '")'):
        if row['origin'] == 'pk':
            continue
        indexes.append({'name':row['name'], 'unique':bool(row['unique']), 'columns':[item['name'] for item in db.execute('PRAGMA index_info("' + row['name'] + '")')]})
    meta[name] = {'columns':columns, 'foreign_keys':foreign_keys, 'indexes':indexes, 'source_count':db.execute('SELECT COUNT(*) FROM "' + name + '"').fetchone()[0]}
order = []
while len(order) < len(tables):
    ready = [name for name in tables if name not in order and all(fk['table'] in order for fk in meta[name]['foreign_keys'])]
    if not ready:
        raise RuntimeError('Dependencias cíclicas: definir carga diferida explícita.')
    order += ready

def q(name):
    assert re.fullmatch(r'[A-Za-z_][A-Za-z_0-9]*', name)
    return '`' + name + '`'

lengths = {'email':255,'password':255,'remember_token':100,'cedula':30,'numero_telefono':30,'serial':100,'estado_activo':50,'estado_reporte':50,'urgencia':50,'tipo_tecnico':100,'nivel_alerta':50,'tipo_alerta':100,'estado_alerta':50,'currency':3,'clave_envio':64,'submission_key':64,'submission_hash':64,'hora':10,'ip_address':45,'key':255,'id':255}
def mariatype(table, column):
    name = column['name']; typ = column['type'].lower()
    if typ == 'integer':
        if name == 'id' or name.endswith('_id') or name == 'updated_by':
            return 'BIGINT UNSIGNED'
        if name in ('revision','revision_flujo','frecuencia_service','interval_days','quantity','unit_cost_minor','amount_minor','attempts','year'):
            return 'BIGINT UNSIGNED'
        return 'BIGINT'
    if typ == 'tinyint(1)': return 'BOOLEAN'
    if typ in ('numeric','decimal'): return 'DECIMAL(5,2)'
    if typ == 'double': return 'DOUBLE'
    if typ == 'date': return 'DATE'
    if typ == 'datetime': return 'DATETIME'
    if table == 'reportes' and name == 'intervenciones': return 'JSON'
    if typ == 'text': return 'LONGTEXT'
    if typ == 'varchar':
        result = 'VARCHAR(' + str(lengths.get(name,255)) + ')'
        if name in ('estado_activo','estado_reporte','urgencia','rol','tipo_tecnico','estado_alerta'):
            result += ' COLLATE utf8mb4_bin'
        return result
    raise RuntimeError('Tipo sin mapear: ' + table + '.' + name + '=' + typ)

schema = ["-- Derivado de metadatos de SQLite actual; no contiene datos del origen.",
          "-- CREATE DATABASE falla si el nombre ya existe. Ejecutar sin --force.",
          f"CREATE DATABASE `{DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
          f"USE `{DATABASE}`;", "SET time_zone = '+00:00';"]
for name in order:
    info = meta[name]; lines=[]
    for column in info['columns']:
        field = column['name']
        definition = q(field) + ' ' + mariatype(name,column)
        definition += ' NOT NULL' if column['notnull'] or column['pk'] else ' NULL'
        if field == 'id' and 'autoincrement' in tables[name].lower(): definition += ' AUTO_INCREMENT'
        default = column['dflt_value']
        if name == 'reportes' and field == 'estado_reporte': default = "'Recibido/Derivado'"
        if default is not None:
            while default.startswith('(') and default.endswith(')'):default=default[1:-1]
            definition += ' DEFAULT ' + default
        lines.append(definition)
        if column['type'].lower() == 'tinyint(1)': lines.append('CHECK (' + q(field) + ' IN (0,1))')
    primary = [column['name'] for column in sorted(info['columns'],key=lambda item:item['pk']) if column['pk']]
    if primary: lines.append('PRIMARY KEY (' + ','.join(map(q,primary)) + ')')
    for index in info['indexes']:
        lines.append(('UNIQUE KEY ' if index['unique'] else 'KEY ') + q(index['name']) + ' (' + ','.join(map(q,index['columns'])) + ')')
    for fk in info['foreign_keys']:
        lines.append('CONSTRAINT ' + q('fk_' + name + '_' + str(fk['id'])) + ' FOREIGN KEY (' + q(fk['from']) + ') REFERENCES ' + q(fk['table']) + '(' + q(fk['to']) + ') ON DELETE ' + fk['on_delete'] + ' ON UPDATE ' + fk['on_update'])
    checks = re.findall(r'check \(("\w+" in \([^)]*\))\)',tables[name],re.I)
    for check in checks: lines.append('CHECK (' + check.replace('"','`') + ')')
    if name == 'consumibles': lines.append('CHECK (`nivel` BETWEEN 0 AND 100)')
    schema.append('CREATE TABLE ' + q(name) + ' (\n  ' + ',\n  '.join(lines) + '\n) ENGINE=InnoDB;')
(ROOT / 'sql' / '01_esquema.sql').write_text('\n\n'.join(schema)+'\n')


data = {}
seed = [f"USE `{DATABASE}`;", "SET time_zone = '+00:00';", "START TRANSACTION;"]
def literal(value):
    if value is None: return 'NULL'
    if isinstance(value, (int,float)): return str(value)
    raw = value if isinstance(value, bytes) else value.encode('utf8')
    return "CONVERT(X'" + raw.hex() + "' USING utf8mb4)"
for table in order:
    columns = [column['name'] for column in meta[table]['columns']]
    primary = [column['name'] for column in sorted(meta[table]['columns'],key=lambda item:item['pk']) if column['pk']]
    sorting = ' ORDER BY ' + ','.join(q(name) for name in primary) if primary else ''
    data[table] = [dict(row) for row in db.execute('SELECT * FROM '+q(table)+sorting)]
    for row in data[table]:
        seed.append('INSERT INTO '+q(table)+' ('+','.join(map(q,columns))+') VALUES ('+','.join(literal(row[name]) for name in columns)+');')
seed.append('COMMIT;')
for table,sequence in db.execute('SELECT name,seq FROM sqlite_sequence'):
    if table in meta: seed.append('ALTER TABLE '+q(table)+' AUTO_INCREMENT = '+str(sequence+1)+';')
(ROOT/'sql/02_datos_privados.sql').write_text('\n'.join(seed)+'\n')
(ROOT/'snapshot.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
(ROOT/'mapping.json').write_text(json.dumps({'database':DATABASE,'source_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'order':order,'tables':meta,'total':sum(len(rows) for rows in data.values())},ensure_ascii=False,indent=2)+'\n')
for file in ROOT.rglob('*'):
    if file.is_file(): file.chmod(0o600)
db.close()
print(json.dumps({'tables':len(order),'rows':sum(len(rows) for rows in data.values()),'private_export':str(ROOT)},ensure_ascii=False))
