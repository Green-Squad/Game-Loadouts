#!/usr/bin/env python3
"""Bounded warm benchmark, stdlib only. Never load-test gameloadouts.com."""
import argparse, concurrent.futures, http.client, json, math, pathlib, re, statistics, time, urllib.parse

def percentile(xs, p):
    return sorted(xs)[max(0, math.ceil(len(xs)*p)-1)]

def memory(pid):
    if not pid: return None
    try:
        fields=dict(line.split(':',1) for line in pathlib.Path(f'/proc/{pid}/status').read_text().splitlines() if ':' in line)
        return {k:int(fields[k].strip().split()[0]) for k in ('VmRSS','VmHWM') if k in fields}
    except (OSError,ValueError): return None

def run(base, route, count, concurrency, warmups):
    u=urllib.parse.urlsplit(base)
    cls=http.client.HTTPSConnection if u.scheme=='https' else http.client.HTTPConnection
    def worker(n):
        conn=cls(u.hostname,u.port,timeout=20); results=[]
        try:
            for i in range(n):
                path=u.path.rstrip('/')+route+('&' if '?' in route else '?')+'bench='+str(time.monotonic_ns())
                start=time.perf_counter_ns();conn.request('GET',path,headers={'Cache-Control':'no-cache','Pragma':'no-cache','Accept-Encoding':'identity'})
                response=conn.getresponse();payload=response.read();elapsed=(time.perf_counter_ns()-start)/1e6
                if response.status!=200:raise RuntimeError(f'{route}: HTTP {response.status}')
                timing=response.getheader('Server-Timing','');m=re.search(r'db;dur=([\d.]+)',timing)
                q=response.getheader('X-DB-Queries')
                results.append((elapsed,float(m.group(1)) if m else None,int(q) if q is not None else None,len(payload)))
        finally:conn.close()
        return results
    worker(warmups)
    start=time.perf_counter()
    with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as pool:
        groups=list(pool.map(worker,[count//concurrency+(i<count%concurrency) for i in range(concurrency)]))
    wall=time.perf_counter()-start;rows=[x for group in groups for x in group];lat=[r[0] for r in rows];db=[r[1] for r in rows if r[1] is not None];qs=sorted(set(r[2] for r in rows if r[2] is not None))
    return {'route':route,'concurrency':concurrency,'requests':len(rows),'p50_ms':percentile(lat,.50),'p95_ms':percentile(lat,.95),'mean_ms':statistics.mean(lat),'requests_per_second':len(rows)/wall,'db_mean_ms':statistics.mean(db) if db else None,'db_p95_ms':percentile(db,.95) if db else None,'query_counts':qs,'response_bytes':sorted(set(r[3] for r in rows))}

def main():
    p=argparse.ArgumentParser();p.add_argument('--base',default='http://127.0.0.1:8080');p.add_argument('--requests',type=int,default=200);p.add_argument('--concurrency',type=int,nargs='+',default=[1,8]);p.add_argument('--warmups',type=int,default=20);p.add_argument('--route',action='append');p.add_argument('--pid',type=int);p.add_argument('--output');p.add_argument('--allow-isolated-target',action='store_true');a=p.parse_args()
    host=urllib.parse.urlsplit(a.base).hostname or ''
    if host=='gameloadouts.com' or host.endswith('.gameloadouts.com'):p.error('Production site load testing is prohibited')
    if host not in ('localhost','127.0.0.1','::1') and not a.allow_isolated_target:p.error('Remote benchmarks require --allow-isolated-target and a separately provisioned test service')
    if not 1<=a.requests<=10000 or not all(1<=c<=32 for c in a.concurrency):p.error('Use 1..10000 requests and concurrency 1..32')
    routes=a.route or ['/','/games','/Call+of+Duty+Modern+Warfare','/Call+of+Duty+Modern+Warfare/M4A1','/Call+of+Duty+Modern+Warfare/M4A1/1','/stats','/search_weapons?query=M4']
    report={'base':a.base,'warmups_per_workload':a.warmups,'cache':'no application caching; CDN must be bypassed','rss_before_kib':memory(a.pid),'measurements':[run(a.base,r,a.requests,c,a.warmups) for r in routes for c in a.concurrency],'rss_after_kib':memory(a.pid)}
    data=json.dumps(report,indent=2);print(data)
    if a.output:pathlib.Path(a.output).write_text(data+'\n')
if __name__=='__main__':main()
