import sys; sys.path.insert(0,sys.argv[1]); from companies import COMPANIES
from PIL import Image,ImageDraw,ImageFont
out=sys.argv[2]; S=512
fonts=["/System/Library/Fonts/Avenir Next.ttc","/System/Library/Fonts/Supplemental/Futura.ttc","/System/Library/Fonts/Supplemental/GillSans.ttc"]
for i,(slug,name,ind,size,fy,hq,color,mark,*_) in enumerate(COMPANIES):
  im=Image.new("RGBA",(S,S),(0,0,0,0)); d=ImageDraw.Draw(im)
  style=i%4; c=color
  if style==0: d.rounded_rectangle([0,0,S,S],radius=112,fill=c); fg="white"
  elif style==1: d.ellipse([0,0,S,S],fill=c); fg="white"
  elif style==2: d.rounded_rectangle([0,0,S,S],radius=112,fill="#FFFFFF"); d.rounded_rectangle([28,28,S-28,S-28],radius=90,outline=c,width=26); fg=c
  else:
    d.ellipse([0,0,S,S],fill="#FFFFFF"); d.ellipse([22,22,S-22,S-22],fill=c); fg="white"
  fp=fonts[i%len(fonts)]; idx=2 if "Avenir" in fp else (1 if "Futura" in fp else 3)
  try: f=ImageFont.truetype(fp,int(S*(0.48 if len(mark)==1 else 0.36)),index=idx)
  except Exception: f=ImageFont.truetype(fp,int(S*0.4))
  bb=d.textbbox((0,0),mark,font=f); w=bb[2]-bb[0]; h=bb[3]-bb[1]
  d.text(((S-w)/2-bb[0],(S-h)/2-bb[1]),mark,font=f,fill=fg)
  im=im.resize((256,256),Image.LANCZOS); im.save(f"{out}/{slug}.webp","WEBP",quality=90)
print("ok",len(COMPANIES))
