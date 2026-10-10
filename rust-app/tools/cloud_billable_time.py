#!/usr/bin/env python3
"""Read a test service's Cloud Monitoring billable instance time; never log tokens."""
import argparse,datetime,json,subprocess,urllib.parse,urllib.request
p=argparse.ArgumentParser();p.add_argument('--project',required=True);p.add_argument('--service',required=True);p.add_argument('--start',required=True,help='UTC RFC3339');p.add_argument('--end',required=True,help='UTC RFC3339');a=p.parse_args()
if a.service.lower() in ('gameloadouts','game-loadouts'):p.error('Use a separately named comparison service')
token=subprocess.check_output(['gcloud','auth','print-access-token'],text=True).strip()
params={'filter':f'metric.type="run.googleapis.com/container/billable_instance_time" AND resource.type="cloud_run_revision" AND resource.labels.service_name="{a.service}"','interval.startTime':a.start,'interval.endTime':a.end,'view':'FULL'}
rows=[]
while True:
 url='https://monitoring.googleapis.com/v3/projects/'+urllib.parse.quote(a.project,safe='')+'/timeSeries?'+urllib.parse.urlencode(params)
 req=urllib.request.Request(url,headers={'Authorization':'Bearer '+token})
 try:
  with urllib.request.urlopen(req,timeout=30) as response:result=json.load(response)
 except urllib.error.HTTPError as e:raise SystemExit(f'Monitoring API returned HTTP {e.code}; check IAM and metric delay')
 rows+=result.get('timeSeries',[])
 if not result.get('nextPageToken'):break
 params['pageToken']=result['nextPageToken']
print(json.dumps({'service':a.service,'start':a.start,'end':a.end,'billable_instance_seconds':sum(float(point['value'].get('doubleValue',point['value'].get('int64Value',0))) for series in rows for point in series.get('points',[])),'series':rows},indent=2))
