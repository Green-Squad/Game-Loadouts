"""Exercise the original forms against isolated demo containers only."""
import requests,json,re,time,os,urllib.parse,subprocess,gzip
from pathlib import Path
from bs4 import BeautifulSoup
base=os.environ.get('DEMO_BASE_URL','http://127.0.0.1:8080');assert base=='http://127.0.0.1:8080'
catalog=json.loads(gzip.open('hand-forged/catalog.json.gz','rt').read())
for kind in ['games','weapons','attachments']:
 for row in catalog[kind]:
  for field in ['image_url','thumb_url','header_url']:
   if row.get(field): assert (Path('public')/urllib.parse.unquote(row[field])).is_file(),row[field]
session=requests.Session()
def req(path,fields=None,files=None):
 response=session.get(base+path,timeout=30) if fields is None else session.post(base+path,data=fields,files=files,headers={'Referer':base+path},timeout=30)
 response.raise_for_status();return response

def token(html):
 t=BeautifulSoup(html,'html.parser').select_one('input[name=_token]');assert t,'Original form CSRF token missing';return t['value']

def query(sql):
 return subprocess.check_output(['docker','exec','hf-database','mysql','--socket=/tmp/demo.sock','-uroot','-NBe',sql,'game_loadouts_hand_forged'],text=True).strip()
for i in range(180):
 try:
  home=req('/');break
 except Exception:time.sleep(1)
else:raise AssertionError('Startup failed')
Path('hand-forged/test-results').mkdir(exist_ok=True)
Path('hand-forged/test-results/startup.json').write_text(json.dumps({'startup_seconds':round(time.time()-float(os.environ.get('HAND_FORGED_STARTED_AT',time.time())),3)}))
assert 'Hand-Forged Edition' in home.text and 'Handwritten code from before the prompt.' in home.text
assert query('SELECT COUNT(*) FROM games')=='11'
assert query('SELECT COUNT(*) FROM weapons')=='843'
assert query('SELECT COUNT(*) FROM attachments')=='5855'
assert query("SELECT COUNT(*) FROM users WHERE email NOT LIKE '%@handforged.invalid'")=='0'
for path in ['/games','/stats','/Call+of+Duty+Black+Ops+7','/Call+of+Duty+Black+Ops+7/AK-27']:
 req(path)
styles=BeautifulSoup(home.text,'html.parser').select('link[rel=stylesheet]')
for link in styles:
 url=urllib.parse.urlsplit(link['href']);assert url.netloc in ('','127.0.0.1:8080');req(url.path)
login=req('/login');signed=req('/login',{'_token':token(login.text),'emailOrUsername':'admin@handforged.invalid','password':'HandForgedDemo!','remember':'remember'})
assert 'successfully logged in' in signed.text
assert 'Admin' in req('/admin').text
cookies=[c.name for c in session.cookies];assert 'gameloadouts_hand_forged_session' in cookies and 'laravel_session' not in cookies
assert 'gameloadouts_hand_forged_remember' in cookies
# Create/update an original game using its image and header upload fields.
image=Path('public/uploads/Call+of+Duty+Black+Ops+7-AK-27.png').read_bytes()
form=req('/admin/game/create');files={'image':('demo.png',image,'image/png'),'header_image':('header.png',image,'image/png')}
created=req('/admin/game',{'_token':token(form.text),'id':'HandForgedValidation','live':'1','short_name':'HF','theme_color':'green'},files)
assert 'successfully created' in created.text
assert query("SELECT COUNT(*) FROM games WHERE id='HandForgedValidation'")=='1'
req('/uploads/HandForgedValidation.png');req('/uploads/thumb/HandForgedValidation.png');req('/uploads/header/HandForgedValidation.png')
form=req('/admin/game/HandForgedValidation/edit')
updated=req('/admin/game/HandForgedValidation',{'_token':token(form.text),'_method':'PUT','id':'HandForgedValidation','live':'1','short_name':'HFU','theme_color':'red'},files)
assert 'successfully updated' in updated.text
assert query("SELECT theme_color FROM games WHERE id='HandForgedValidation'")=='red'
# Original attachment upload, relationship assignment, and CSV import.
form=req('/admin/HandForgedValidation/attachment/create')
created=req('/admin/HandForgedValidation/attachment/create',{'_token':token(form.text),'name':'Demo Optic','slot':'Optic'},{'image':('optic.png',image,'image/png')})
assert 'successfully created' in created.text
attachment=query("SELECT id FROM attachments WHERE game_id='HandForgedValidation' AND name='Demo Optic'")
form=req('/admin/HandForgedValidation/weapon/create')
created=req('/admin/HandForgedValidation/weapon/create',{'_token':token(form.text),'name':'Demo Rifle','type':'Assault Rifle','min_attachments':'0','max_attachments':'1','attachments[]':attachment},{'image':('weapon.png',image,'image/png')})
assert 'successfully created' in created.text
weapon=query("SELECT id FROM weapons WHERE game_id='HandForgedValidation' AND name='Demo Rifle'")
assert query('SELECT COUNT(*) FROM attachment_weapon WHERE weapon_id='+weapon+' AND attachment_id='+attachment)=='1'
form=req('/admin/HandForgedValidation/import')
csv='Weapon name,,Imported Demo Rifle\nWeapon type,,Assault Rifle\nMinimum attachments,,0\nMaximum attachments,,1\nAttachment,Slot,\nImported Demo Optic,Optic,X\n'
req('/admin/HandForgedValidation/import',{'_token':token(form.text)},{'spreadsheet':('demo.csv',csv.encode(),'text/csv')})
assert query("SELECT COUNT(*) FROM weapons WHERE game_id='HandForgedValidation' AND name='Imported Demo Rifle'")=='1'
assert query("SELECT COUNT(*) FROM attachment_weapon aw JOIN weapons w ON w.id=aw.weapon_id JOIN attachments a ON a.id=aw.attachment_id WHERE w.name='Imported Demo Rifle' AND a.name='Imported Demo Optic'")=='1'
# Submit with the original validation and vote/detach the same combination.
form=req('/HandForgedValidation/Demo%20Rifle')
submitted=req('/HandForgedValidation/Demo%20Rifle',{'_token':token(form.text),'Optic':attachment})
assert query('SELECT COUNT(*) FROM loadouts WHERE weapon_id='+weapon)=='1'
loadout=query('SELECT id FROM loadouts WHERE weapon_id='+weapon)
req('/HandForgedValidation/Demo%20Rifle/'+loadout+'/detach',{'_token':token(submitted.text)})
assert query("SELECT COUNT(*) FROM loadout_user WHERE loadout_id="+loadout+" AND user_id='admin@handforged.invalid'")=='0'
req('/HandForgedValidation/Demo%20Rifle/'+loadout+'/upvote',{'_token':token(submitted.text)})
assert query("SELECT COUNT(*) FROM loadout_user WHERE loadout_id="+loadout+" AND user_id='admin@handforged.invalid'")=='1'
# Delete the temporary game's rows through the original resource endpoint.
form=req('/admin/game/HandForgedValidation/delete')
req('/admin/game/HandForgedValidation',{'_token':token(form.text),'_method':'DELETE'})
assert query("SELECT COUNT(*) FROM games WHERE id='HandForgedValidation'")=='0'
assert query('SELECT COUNT(*) FROM weapons WHERE id='+weapon)=='0'
# Generic search uses the original results view, backed by local rows.
search=req('/search',{'_token':token(home.text),'query':'AK-27'});assert 'AK-27' in search.text and 'Search Results' in search.text
# Capture a genuine original registration/confirmation message without SMTP.
req('/logout');form=req('/join')
joined=req('/join',{'_token':token(form.text),'username':'HistoricalSignup','email':'signup@handforged.invalid','password':'DemoSignup123!','password_confirmation':'DemoSignup123!'})
assert query("SELECT COUNT(*) FROM users WHERE email='signup@handforged.invalid'")=='1'
inbox=req('/hand-forged/mail');assert 'Captured demo email' in inbox.text
assert 'signup@handforged.invalid' not in query("SELECT email FROM users WHERE role='Admin'")
assert 'google-analytics.com' not in home.text and 'adsbygoogle' not in home.text
Path('hand-forged/test-results').mkdir(exist_ok=True)
Path('hand-forged/test-results/public.html').write_text(home.text)
print('PASS original public/admin login, upload, CRUD, attachment relationship, CSV import, submission, vote/detach, local search/mail and independent cookies')
