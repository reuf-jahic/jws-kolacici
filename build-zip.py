"""python build-zip.py  ->  dist/jws-kolacici.zip (folder jws-kolacici/ unutra; bez README i build skripte).
Ide kao prilog GitHub izdanja; plugin se ažurira iz tog priloga (inc/azuriranje.php)."""
import os, zipfile
HERE = os.path.dirname(os.path.abspath(__file__))
FAJLOVI = ['jws-kolacici.php', 'uninstall.php', 'LICENSE', 'js/jws-kolacici.js'] + ['inc/' + f for f in sorted(os.listdir(os.path.join(HERE, 'inc'))) if f.endswith('.php')]
os.makedirs(os.path.join(HERE, 'dist'), exist_ok=True)
out = os.path.join(HERE, 'dist', 'jws-kolacici.zip')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for rel in FAJLOVI:
        data = open(os.path.join(HERE, rel), 'rb').read().replace(b'\r\n', b'\n')
        z.writestr('jws-kolacici/' + rel, data)
print(out, len(FAJLOVI), 'fajlova')
