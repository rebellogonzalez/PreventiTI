#!/usr/bin/env python3
"""Contrasta cada campo importado y las claves contra la instantánea privada."""
import argparse
from datetime import date,datetime
from decimal import Decimal
import hashlib,json,sys
from pathlib import Path

p=argparse.ArgumentParser();p.add_argument('--home',required=True,type=Path);p.add_argument('--export',required=True,type=Path);p.add_argument('--database',choices=['preventiti','preventiti_restore_tests']);args=p.parse_args()
home=args.home.resolve(strict=True);export=args.export.resolve(strict=True)
sys.path.insert(0,str(next((home/'runtime/paquetes').glob('pymysql-*.whl'))))
import pymysql
credentials=json.loads((home/'privado/credentials.json').read_text())
meta=json.loads((export/'mapping.json').read_text());snapshot=json.loads((export/'snapshot.json').read_text())
if args.database:meta['database']=args.database
conn=pymysql.connect(unix_socket=str(home/'runtime/mariadb.sock'),user='root',password=credentials['root'],database=meta['database'],charset='utf8mb4',autocommit=True)
cursor=conn.cursor()
cursor.execute('SELECT VERSION(),@@datadir,@@skip_networking');version,datadir,no_tcp=cursor.fetchone()
assert version.startswith('10.11.') and Path(datadir).resolve()==home/'runtime/datos' and no_tcp
counts={};fields=0
for table in meta['order']:
    info=meta['tables'][table];columns=[row['name'] for row in info['columns']]
    pk=[row['name'] for row in sorted(info['columns'],key=lambda row:row['pk']) if row['pk']]
    ordering=' ORDER BY '+','.join('`'+name+'`' for name in pk) if pk else ''
    cursor.execute('SELECT '+','.join('`'+name+'`' for name in columns)+' FROM `'+table+'`'+ordering)
    rows=cursor.fetchall();counts[table]=len(rows)
    if table=='migrations':
        source_ids={row['id'] for row in snapshot[table]};rows=[row for row in rows if row[columns.index('id')] in source_ids]
    assert len(rows)==len(snapshot[table]),table+': conteo distinto'
    for actual,wanted in zip(rows,snapshot[table]):
        for column,value in zip(columns,actual):
            expected=wanted[column]
            if isinstance(value,(datetime,date)):value=str(value)
            if isinstance(value,Decimal):assert value==Decimal(str(expected)),table+'.'+column+': número distinto'
            else:assert value==expected,table+'.'+column+': dato distinto'
            fields+=1
    for index in info['indexes']:
        cursor.execute('SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s ORDER BY SEQ_IN_INDEX',(meta['database'],table,index['name']))
        found=cursor.fetchall()
        assert [row[0] for row in found]==index['columns'],table+': falta índice '+index['name']
        assert all(bool(1-row[1])==index['unique'] for row in found),table+': índice diferente'
    cursor.execute("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",(meta['database'],table))
    assert [row[0] for row in cursor.fetchall()]==pk,table+': PK distinta'
    for fk in info['foreign_keys']:
        cursor.execute('SELECT k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=%s AND k.TABLE_NAME=%s AND k.COLUMN_NAME=%s AND k.REFERENCED_TABLE_NAME IS NOT NULL',(meta['database'],table,fk['from']))
        found=cursor.fetchall()
        normalize=lambda value:'RESTRICT' if value=='NO ACTION' else value
        expected=(fk['table'],fk['to'],normalize(fk['on_delete']),normalize(fk['on_update']))
        assert expected in [(a,b,normalize(c),normalize(d)) for a,b,c,d in found],table+': FK o acción referencial distinta'
cursor.execute('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=%s AND REFERENCED_TABLE_NAME IS NOT NULL',(meta['database'],))
foreign_keys=cursor.fetchall()
for child,column,parent,key in foreign_keys:
    cursor.execute('SELECT COUNT(*) FROM `'+child+'` c LEFT JOIN `'+parent+'` p ON c.`'+column+'`=p.`'+key+'` WHERE c.`'+column+'` IS NOT NULL AND p.`'+key+'` IS NULL')
    assert cursor.fetchone()[0]==0,child+': FK huérfana'
cursor.execute('SELECT COUNT(*) FROM historial_estados_activo');assert cursor.fetchone()[0]==0
cursor.execute('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=%s',(meta['database'],));triggers=cursor.fetchone()[0]
cursor.execute('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=%s',(meta['database'],));routines=cursor.fetchone()[0]
assert (triggers,routines)==(10,3),'Faltan triggers o rutinas'
cursor.execute('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s',(meta['database'],));engines=cursor.fetchall()
assert len(engines)==32 and all(row[0]=='InnoDB' for row in engines),'Esquema o motor incompleto'
report={'version':version,'database':meta['database'],'source_rows':meta['total'],'original_fields_compared':fields,'counts':counts,'source_accounts_and_password_hashes_identical':True,'all_original_columns_identical':True,'primary_and_named_indexes_preserved':True,'original_foreign_keys_and_actions_preserved':True,'innodb_tables':len(engines),'foreign_keys_without_orphans':len(foreign_keys),'triggers':triggers,'routines':routines,'new_asset_history_rows':0}
filename='migration_comparison.json' if meta['database']=='preventiti' else 'restore_comparison.json'
(home/'evidencia'/filename).write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({key:value for key,value in report.items() if key!='counts'},ensure_ascii=False))
conn.close()
