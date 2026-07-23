import re
import subprocess
import sys

with open('documentacion.php', 'r', encoding='utf-8') as f:
    html = f.read()

match = re.search(r'<script>(.*?)</script>', html, re.DOTALL)
if match:
    js = match.group(1)
    js = re.sub(r'<\?=.*?\?>', '1', js)
    with open('temp.js', 'w', encoding='utf-8') as f:
        f.write(js)
    
    result = subprocess.run(['node', '-c', 'temp.js'], capture_output=True, text=True)
    print(result.stdout)
    print(result.stderr)
    if result.returncode != 0:
        sys.exit(1)
else:
    print("No script found")
