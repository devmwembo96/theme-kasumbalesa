"""Convertit un fichier MQL5 en C++ vérifiable par g++ -fsyntax-only (avec mql5mock.h).

Les numéros de ligne sont conservés : une erreur g++ à la ligne N désigne la ligne N du fichier MQL5.
Usage : python3 -I mql2cpp.py fichier.mq5 sortie.cpp
"""
import re
import sys

src_path, out_path = sys.argv[1], sys.argv[2]
lines = open(src_path, encoding='utf-8').read().split('\n')

defines = set()
out = []
for line in lines:
    s = line
    if re.match(r'\s*#property\b', s) or re.match(r'\s*#include\s*<', s) or re.match(r'\s*input\s+group\b', s):
        s = ''
    else:
        s = re.sub(r'^(\s*)(?:input|sinput)\s+', r'\1', s)
        m = re.match(r'\s*#define\s+(\w+)\s+"((?:[^"\\]|\\.)*)"\s*(//.*)?$', s)
        if m:
            s = '#define %s std::string("%s")' % (m.group(1), m.group(2))
        m2 = re.match(r'\s*#define\s+(\w+)', s)
        if m2:
            defines.add(m2.group(1))
    out.append(s)
code = '\n'.join(out)

# Couleurs C'r,g,b'
code = re.sub(r"C'(\d+)\s*,\s*(\d+)\s*,\s*(\d+)'", r'MQLRGB(\1,\2,\3)', code)
# Tableaux dynamiques : « type nom[] » et « const type &nom[] »
code = re.sub(r'\b(const\s+)?([A-Za-z_]\w*)(\s+&?\s*|\s*&\s*)([A-Za-z_]\w*)\s*\[\s*\]',
              lambda m: '%sMArr<%s> %s%s' % (m.group(1) or '', m.group(2), '&' if '&' in m.group(3) else '', m.group(4)), code)
# « MArr<T> a, b[] » : le type vaut pour toute la déclaration
while True:
    code2 = re.sub(r'(MArr<[^>]+>\s+\w+(?:\s*,\s*\w+)*)\s*,\s*(\w+)\s*\[\s*\]', r'\1, \2', code)
    if code2 == code:
        break
    code = code2


def strip_noncode(text):
    """Remplace commentaires, chaînes et caractères par des espaces (même longueur)."""
    res = []
    i, n = 0, len(text)
    while i < n:
        c = text[i]
        if text.startswith('//', i):
            j = text.find('\n', i)
            j = n if j < 0 else j
            res.append(' ' * (j - i))
            i = j
        elif text.startswith('/*', i):
            j = text.find('*/', i + 2)
            j = n if j < 0 else j + 2
            res.append(re.sub(r'[^\n]', ' ', text[i:j]))
            i = j
        elif c == '"' or c == "'":
            j = i + 1
            while j < n and text[j] != c:
                j += 2 if text[j] == '\\' else 1
            j = min(n, j + 1)
            res.append(c + ' ' * (j - i - 2) + c if j - i >= 2 else ' ' * (j - i))
            i = j
        else:
            res.append(c)
            i += 1
    return ''.join(res)


def wrap_strings(text):
    res = []
    i, n = 0, len(text)
    while i < n:
        if text.startswith('//', i):
            j = text.find('\n', i)
            j = n if j < 0 else j
            res.append(text[i:j]); i = j
        elif text.startswith('/*', i):
            j = text.find('*/', i + 2)
            j = n if j < 0 else j + 2
            res.append(text[i:j]); i = j
        elif text[i] == "'":
            j = i + 1
            while j < n and text[j] != "'":
                j += 2 if text[j] == '\\' else 1
            res.append(text[i:j + 1]); i = j + 1
        elif text[i] == '"':
            j = i + 1
            while j < n and text[j] != '"':
                j += 2 if text[j] == '\\' else 1
            lit = text[i:j + 1]
            line_start = text.rfind('\n', 0, i) + 1
            if text[line_start:i].lstrip().startswith('#'):
                res.append(lit)
            else:
                res.append('std::string(' + lit + ')')
            i = j + 1
        else:
            res.append(text[i]); i += 1
    return ''.join(res)


code = wrap_strings(code)
plain = strip_noncode(code)
assert len(plain) == len(code)

# Définitions de fonctions au niveau 0 : prototypes (avec valeurs par défaut), définitions sans défauts
protos = []
edits = []          # (début, fin, texte) dans code
depth = 0
i = 0
n = len(plain)
first_func = None
sig_re = re.compile(r'([A-Za-z_][\w<>:]*(?:\s*[&*])?)\s+([A-Za-z_]\w*)\s*\(')
while i < n:
    c = plain[i]
    if c == '{':
        depth += 1
    elif c == '}':
        depth -= 1
    elif depth == 0 and (i == 0 or plain[i - 1] == '\n'):
        m = sig_re.match(plain, i)
        if m and m.group(1) not in ('return', 'else', 'struct', 'class', 'enum', 'typedef', 'define'):
            # fin de la liste de paramètres
            k = m.end()
            par = 1
            while k < n and par > 0:
                if plain[k] == '(':
                    par += 1
                elif plain[k] == ')':
                    par -= 1
                k += 1
            rest = plain[k:k + 400].lstrip()
            if rest.startswith('{'):
                sig = code[i:k]
                params_start = m.end()
                params = code[params_start:k - 1]
                protos.append(' '.join(sig.split()) + ';')
                # défauts retirés de la définition (valeur jusqu'à la virgule ou la fin)
                plain_params = plain[params_start:k - 1]
                new_params = list(params)
                for dm in re.finditer(r'=\s*[^,]+', plain_params):
                    for q in range(dm.start(), dm.end()):
                        if new_params[q] != '\n':
                            new_params[q] = ' '
                edits.append((params_start, k - 1, ''.join(new_params)))
                if first_func is None:
                    first_func = i
                i = k
                continue
    i += 1

for a, b, t in sorted(edits, reverse=True):
    code = code[:a] + t + code[b:]

# Prototypes ajoutés en tête de la première fonction (sur la même ligne : numérotation conservée)
if first_func is not None:
    code = code[:first_func] + ' '.join(protos) + ' ' + code[first_func:]

# Constantes MQL5 inconnues : déclarées à 0
plain2 = strip_noncode(code)
known = set(defines) | {'MQLRGB', 'MArr', 'NULL', 'SEEK_END', 'SEEK_SET', 'SEEK_CUR', 'EOF', 'INT_MAX', 'INT_MIN', 'UINT_MAX', 'LONG_MAX', 'LONG_MIN', 'ULONG_MAX', 'DBL_MAX', 'DBL_MIN', 'CHAR_MAX', 'CHAR_MIN', 'UCHAR_MAX', 'SHRT_MAX', 'USHRT_MAX'}
for m in re.finditer(r'\benum\s+(\w+)\s*\{([^}]*)\}', plain2):
    known.add(m.group(1))
    for name in re.findall(r'\b([A-Za-z_]\w*)\s*(?:=|,|$)', m.group(2)):
        known.add(name)
# variables globales en majuscules (GV_M1…) : pas des constantes
for m in re.finditer(r'\b(?:string|int|double|bool|datetime|long|uint|ulong|color|char|uchar|short|ushort)\s+([A-Z][A-Z0-9_]*)\b', plain2):
    known.add(m.group(1))
for m in re.finditer(r',\s*([A-Z][A-Z0-9_]*)\s*=', plain2):
    known.add(m.group(1))
mock = open(__file__.replace('mql2cpp.py', 'mql5mock.h'), encoding='utf-8').read()
# types ENUM_… de MQL5 utilisés comme types : int
enum_types = set(re.findall(r'\b(ENUM_[A-Z0-9_]+)\s*(?:&\s*)?[A-Za-z_]\w*', plain2)) | set(re.findall(r'MArr<(ENUM_[A-Z0-9_]+)>', plain2))
known |= set(re.findall(r'typedef\s+[\w\s]+?\s(\w+);', mock))
consts = sorted({x for x in re.findall(r'\b([A-Z][A-Z0-9]*_[A-Z0-9_]+|[A-Z]{3,}[0-9]*)\b', plain2)} - known)
clrs = sorted(set(re.findall(r'\b(clr[A-Z]\w*)\b', plain2)))
enum_types -= known
known |= enum_types
consts = [c for c in consts if c not in enum_types]
head = '#include "mql5mock.h"\n'
head += ' '.join('typedef int %s;' % t for t in sorted(enum_types)) + ' '
head += ' '.join('static const long %s = 0;' % c for c in consts) + ' '
head += ' '.join('static const color %s = 0;' % c for c in clrs) + ' '
code = head.replace('\n', ' ') + code
code = code.replace('#include "mql5mock.h" ', '#include "mql5mock.h"\n', 1)
# la ligne d'en-tête décale tout d'une ligne : on supprime la première ligne vide du fichier MQL5 si possible
open(out_path, 'w', encoding='utf-8').write(code)
print('%d prototypes, %d constantes' % (len(protos), len(consts)))
