import sys,json,random; sys.path.insert(0,sys.argv[1])
from companies import COMPANIES; from people import PEOPLE,ZOOM,TARGET; from roles import ROLES,LEVELS,CITY_PAY,CURRENCY,FX
random.seed(7); src=json.load(open(sys.argv[1]+'/candidates.json'))
EMP=[("Hannah","Price"),("Kenji","Watanabe"),("Fatima","Al-Sayed"),("Oliver","Grant"),("Lucia","Ferraro"),("Thomas","Becker"),("Ana","Silva"),("Patrick","Doyle"),
 ("Yuki","Mori"),("Rebecca","Stone"),("Ahmed","Karim"),("Laura","Jensen"),("Victor","Lam"),("Ingrid","Larsen"),("Samir","Patel"),("Elena","Petrova"),
 ("George","Whitman"),("Nora","Fitzgerald"),("Pierre","Garnier"),("Jade","Morgan"),("Carlos","Vega"),("Helen","Park"),("Mateo","Ruiz"),("Sarah","Lindgren")]
BENEFITS={"default":["Health, dental and vision cover from day one","25 days of paid leave plus public holidays","Annual learning budget","Flexible hours around core collaboration time"],
 "hospitality":["Free meals on shift","Staff discounts across our venues","Paid training and a clear path to promotion"],
 "retail":["Staff discount of 20%","Paid overtime and a yearly bonus","Pension with employer contribution"],
 "healthcare":["Paid professional registration and CPD","Defined-benefit pension","Mental health support for staff"],
 "education":["Small classes and paid planning time","Tuition support for further study","Summer and winter breaks"]}
companies=[]
for i,(slug,name,ind,size,fy,hq,color,mark,tagline,about,tags) in enumerate(COMPANIES):
  fn,ln=EMP[i]
  companies.append(dict(slug=slug,name=name,industry=ind,size=size,founded=str(fy),hq=hq,tagline=tagline,about=about,tags=tags,
    website=f"https://{slug.replace('-','')}.example",linkedin=f"https://www.linkedin.com/company/{slug}-example",logo=f"logos/{slug}.webp",
    employer=dict(first=fn,last=ln,login=f"{fn}.{ln}".lower().replace("'",""),email=f"{fn.lower()}.{ln.lower().replace(' ','')}@{slug.replace('-','')}.example",title=random.choice(["Head of Talent","People Partner","Hiring Manager","Founder and CEO","Recruitment Lead"]))))
def fits(role,ind): return '*' in role[2] or ind in role[2]
jobs=[]
for c in companies:
  spec=[r for r in ROLES if c['industry'] in r[2]]; gen=[r for r in ROLES if '*' in r[2]]; random.shuffle(spec); random.shuffle(gen)
  n=random.randint(6,9) if c['industry'] in ('technology','finance') else random.randint(4,7)
  pool=spec[:max(1,int(n*0.75))]+gen; pool=pool[:n]
  for r in pool[:n]:
    lvl=random.choices(LEVELS,weights=[2,5,4,1])[0]
    if r[0] in ("Barista and Shift Lead","Warehouse Associate","Customer Support Specialist","Marketing Coordinator","Sales Development Representative","Primary School Teacher","Registered Nurse") and lvl[0] in ("Lead ",): lvl=LEVELS[1]
    if r[0] in ("Barista and Shift Lead","Warehouse Associate","Registered Nurse","Primary School Teacher"): lvl=LEVELS[1]
    if any(w in r[0] for w in ("Manager","Partner","Recruiter","Strategist")) and lvl[0]=="Junior ": lvl=LEVELS[1]
    remote = c['hq']=="Remote" or (r[1] in ("Engineering","Data","Design","DevOps","Marketing","Product") and random.random()<0.35)
    loc = "Remote" if c['hq']=="Remote" else c['hq']
    cur = CURRENCY.get(loc,"USD"); pay=r[3]*lvl[2]*CITY_PAY.get(loc,1)*FX[cur]
    lo=int(round(pay*0.9/1000)*1000); hi=int(round(pay*1.15/1000)*1000)
    jtype = "Part-time" if r[0]=="Barista and Shift Lead" and random.random()<.6 else ("Contract" if random.random()<0.08 else ("Internship" if lvl[1]=="Entry Level" and random.random()<0.15 else "Full-time"))
    title=(lvl[0]+r[0]) if not r[0].startswith(("Head","Chief")) else r[0]
    if jtype=="Internship": title=r[0]+" Intern"; lo=int(lo*0.45/1000)*1000; hi=int(hi*0.45/1000)*1000
    posted=random.randint(0,44)
    state = "open"
    roll=random.random()
    if roll<0.04: state="expired"
    elif roll<0.07: state="closed"
    jobs.append(dict(company=c['slug'],title=title,role=r[0],category=r[1],experience=lvl[1],type=jtype,location=loc,remote=remote,
      salary_min=lo,salary_max=hi,currency=cur,salary_type="yearly",posted_days_ago=posted,
      deadline_days=(-random.randint(1,10) if state=="expired" else random.randint(14,60)),state=state,featured=random.random()<0.07,
      summary=r[4],do=r[5],need=r[6],benefits=BENEFITS.get(c['industry'],BENEFITS['default']),tags=c['tags'][:2]))
role_by={r[0]:r for r in ROLES}
UNIS=["University of Toronto","University College London","Technical University of Munich","Sorbonne University","University of Texas at Austin","New York University","National University of Singapore","University of Sydney","McGill University","King's College London","University of California, Berkeley","University of Manchester"]
PAST=["Northwind Partners","Riverside Media","Blue Harbor Bank","Granite Peak Systems","Maple & Oak Retail","Cityline Health","Sunrise Hotels Group","Brightwater Labs","Elm Street Agency","Horizon Freight","Clearview Software","Redwood Learning"]
people=[]
for (pi,cx,first,last,loc,headline,cat,years) in PEOPLE:
  s=src[pi]; z=ZOOM.get(pi)
  roles=[r for r in ROLES if r[1]==cat] or ROLES; base=min(roles,key=lambda r:0 if r[0].lower() in headline.lower() else 1)
  skills=[dict(skill_name=k,proficiency=random.choice(["Expert","Advanced","Advanced","Intermediate"])) for k in base[7]]
  exp=[]; y=2026; left=max(1,years)
  for k in range(min(3,1+years//4)):
    span=max(1,min(left,random.randint(2,5))); start=y-span
    exp.append(dict(company=random.choice(PAST),job_title=headline if k==0 else headline.replace("Senior ","").replace("Lead ","").replace("Head of ",""),employment_type="Full-time",location=loc,
      start_date=f"{start}-{random.randint(1,12):02d}-01",end_date="" if k==0 else f"{y}-{random.randint(1,12):02d}-01",current_role="true" if k==0 else "false",
      description=random.choice(base[5])+".",skills_used=", ".join(base[7][:3])))
    y=start; left-=span
    if left<=0: break
  grad=2026-years-random.randint(0,1)
  people.append(dict(first=first,last=last,login=f"{first}.{last}".lower().replace("'","").replace("á","a"),email=f"{first}.{last}".lower().replace("'","").replace("á","a")+"@mail.example",
    location=loc,headline=headline,category=cat,years=years,open_to_work=random.random()<0.6,
    summary=f"{headline} with {years} year{'s' if years!=1 else ''} of experience. "+base[4].replace('You will','I').replace('your','my').replace('Help us','I help teams').replace('Help the team','I help teams')[:0]+random.choice([
      f"I care about doing careful work and explaining it clearly, and I am looking for a team where {cat.lower()} has a real seat at the table.",
      f"Most proud of the improvements I have made to how teams work, not only what they ship. Based in {loc}.",
      f"I enjoy mentoring, plain-language documentation and steady, sustainable delivery."]),
    experience=exp,education=[dict(institution=random.choice(UNIS),degree_type=random.choice(["BSc","BA","MSc","BEng","MA"]),field_of_study=random.choice(["Computer Science","Business","Economics","Design","Psychology","Nursing","Education","Engineering","English"]),start_date=f"{grad-4}-09-01",end_date=f"{grad}-06-01",gpa="",description="",achievements="")],
    skills=skills,
    photo=dict(openverse_id=s['id'],url=s['url'],thumbnail=s['thumbnail'],creator=s.get('creator') or '',creator_url=s.get('creator_url') or '',license=f"{s['license'].upper()} {s.get('license_version') or ''}".strip(),source=s['source'],landing=s['foreign_landing_url'],crop=dict(cx=cx,zoom=z[0] if z else 1,cy=z[1] if z else 0))))
# applications
COVERS=["Hi {emp}, I am applying for the {job} role at {co}. In my current job I {did}, and I would love to bring the same care to your team.","Dear {emp}, I have followed {co} for a while and the {job} opening is a great match for my experience as a {head}. I am available for a call any time next week.","Hello, I am a {head} based in {loc} and I am excited about the {job} position. I have attached my resume and would be glad to share examples of my work."]
apps=[]
open_jobs=[i for i,j in enumerate(jobs) if j['state']=='open']
for pi,p in enumerate(people):
  ok_lv={0:("Entry Level","Mid Level"),1:("Mid Level","Senior"),2:("Senior","Lead")}[0 if p['years']<3 else (1 if p['years']<8 else 2)]
  want=TARGET.get(p['headline'],[])
  pool=[i for i in open_jobs if jobs[i]['role'] in want and jobs[i]['experience'] in ok_lv and jobs[i]['type']!='Internship'] or [i for i in open_jobs if jobs[i]['role'] in want]
  if not pool: continue
  for ji in random.sample(pool,min(len(pool),random.randint(2,5))):
    j=jobs[ji]; co=[c for c in companies if c['slug']==j['company']][0]
    st=random.choices(["submitted","reviewing","shortlisted","rejected","hired","withdrawn"],weights=[40,22,16,13,5,4])[0]
    apps.append(dict(person=pi,job=ji,status=st,days_ago=random.randint(0,max(0,j['posted_days_ago'])),
      cover=random.choice(COVERS).format(emp=co['employer']['first'],job=j['title'],co=co['name'],did=p['experience'][0]['description'][0].lower()+p['experience'][0]['description'][1:].rstrip('.'),head=p['headline'].lower(),loc=p['location'])))
out=dict(version=1,notes="Realistic QA dataset. Companies and people are invented; photos are CC0 from Openverse (see each photo block).",companies=companies,jobs=jobs,people=people,applications=apps)
json.dump(out,open(sys.argv[2],'w'),indent=1,ensure_ascii=False)
print(len(companies),"companies",len(jobs),"jobs",len(people),"people",len(apps),"applications")
from collections import Counter; print(Counter(a['status'] for a in apps)); print(Counter(j['state'] for j in jobs)); print(Counter(j['category'] for j in jobs))
