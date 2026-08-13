"""Iyesá I, II y III — Cuadernillo (PDF pág. 18-22)."""
from dsl import compas, score, seccion

TITULO = 'Iyesá I, II y III'
MATCH = {'año': 1, 'orden': 9, 'nombre': 'Ixesa I'}

INSTS = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique',
         'timbal', 'agogo', 'palmas']
V = '----------------'

AGOGO = 'x=x=x=x=xx-x-x=='
PALMAS = 'x===x=x=x===x=x='


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, ag=V, pa=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti,
                   'agogo': ag, 'palmas': pa}, **kw)


# ---------------------------------------------------------------- Introducción
introduccion = [
    c(ag=AGOGO, texto='Introducción (Iyesá I) — agogó', dyn='mf'),
    c(ag=AGOGO, pa=PALMAS, texto='Entran las palmas'),
]

# --------------------------------------------------------------------- Llamada
LL_RE = '>x>>x>>x>>>>6(xxxxxx)'
LL_TI = 'xxxx-xxo=o=o=oo-'
llamada = [
    c(rp='f==x-fx=x===----', re=V, texto='Llamada', dyn='f'),
    c(rp='x===------fx=x==', sg='----x=x=x===----', sa='----x=x=x===----',
      sm='----x=x=x===----', re=LL_RE, ti=LL_TI),
    c(rp='x===------fx=x==', sg='----x=x=x===----', sa='----x=x=x===----',
      sm='----x=x=x===----', re=LL_RE, ti=LL_TI),
    c(rp='x===----fx=x=x==', sg='----x=x=--------', sa='----x=x=--------',
      sm='----x=x=--------', re=LL_RE, ti=LL_TI),
]

# --------------------------------------------------------------- Toque Iyesá I
I1 = dict(sg='x===----x===----', sa='x===----x===----', sm='----x=x=----x=x=',
          re='>xx>x=>xx>x=6(xx>>>>)', rp='>x>xxx>x>xx>x>>x',
          ti='o=o=xx-xxo=o==xx', ag=AGOGO, pa=PALMAS)
toque_1 = [c(**I1, repeat_begin=True, repeat_end=True,
             texto='Toque Iyesá I', dyn='mf')]

variacion_1 = [c(**{**I1, 'rp': '6(>>>>xx)xx>x>xx>x>>x'},
                 repeat_begin=True, repeat_end=True,
                 texto='Variación de repique (Iyesá I)')]

# ----------------------------------------------- Llamada para Iyesá II / toque
llamada_2 = [
    c(rp='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      sg='x===----x===----', sa='x===----x===----', sm='----x=x=----x=x=',
      re=LL_RE, ti=LL_TI, texto='Llamada para Iyesá II (sobre Iyesá I)',
      dyn='f'),
    c(rp='x=x=--x=--------', sg='x===---------xx-', sa='x===---------xx-',
      sm='----x=x=--------', re=LL_RE, ti=LL_TI),
]

I2 = dict(sg='----x===-----x=x', sa='----x===-----x=x', sm='-x=x=---x==x=---',
          re='x=>=x=>=x=>=x=>=', rp='x==x=x=x==x=----',
          ti='-x=x=--x=x=-x=x=', ag=AGOGO)
toque_2 = [c(**I2, repeat_begin=True, repeat_end=True,
             texto='Toque Iyesá II', dyn='mf')]

# ------------------------------------------------------------ Iyesá III A / B
LL_SU = '---f=x=x=-x=x=--'

llamada_3a = [
    c(sg=LL_SU, sa=LL_SU, sm='t===t===t===t=x=',
      re='xx>x>xxx>xx>xx>x', ti='-o===o=-x=------',
      rp='x==---------x==-',
      texto='Llamada para Iyesá III A (sobre Iyesá II)', dyn='f'),
]

I3A = dict(sg='---t=-t=-x=t--t=', sa='---t=-t=-x=t--t=', sm='t===t===t===t=x=',
           re='xx>x>xxx>xx>xx>x', rp='x==---------x==-',
           ti='-o===o=-x=------', ag=AGOGO)
toque_3a = [c(**I3A, repeat_begin=True, repeat_end=True,
              texto='Toque Iyesá III A', dyn='mf')]

llamada_3b = [
    c(sg=LL_SU, sa=LL_SU, texto='Llamada para Iyesá III B (sobre Iyesá III A)',
      dyn='f'),
]

I3B = dict(sg='-x=x=-x=--x=x=--', sa='-x=x=-x=--x=x=--', sm='----t=x=x---x=x=',
           re='x=>=x=>=x=>=x=>=', rp='-x==--x=----x==-',
           ti='-o=x=-x=--o=o=--')
toque_3b = [c(**I3B, repeat_begin=True, repeat_end=True,
              texto='Toque Iyesá III B', dyn='mf')]

llamada_3c = [
    c(sg=LL_SU, sa=LL_SU, texto='Llamada para Iyesá III C (sobre Iyesá III B)',
      dyn='f'),
]

I3C = dict(sg='-x=x==-t--x=x=t=', sa='-x=x==-t--x=x=t=', sm='----x=x=xx-t=---',
           re='x=>=x=>=x=>=x=>=', rp='-x=--x=x==-x==--',
           ti='-x=x=-x=--x=x=--')
toque_3c = [c(**I3C, repeat_begin=True, repeat_end=True,
              texto='Iyesá III C', dyn='mf')]

# ---------------------------------------------------------------- Llamada final
ROLL = 'xxxxxxxxxxxxx==='
llamada_final = [
    c(sg=ROLL, sa=ROLL, sm=ROLL, re=ROLL, rp=ROLL, ti=ROLL,
      texto='Llamada final', dyn='p'),
    c(sg='x=x=----x=x=----', sa='x=x=----x=x=----', sm='x=x=----x=x=----',
      re='x=x=----x=x=----', rp='x=x=----x=x=----', ti='x=x=----x=x=----',
      dyn='f'),
    c(sg='x===x=--3(xxx)----', sa='x===x=--3(xxx)----',
      sm='x===x=--3(xxx)----', re='x===x=--3(xxx)----',
      rp='x===x=--3(xxx)----', ti='x===x=--3(xxx)----'),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Introducción (Iyesá I)', introduccion, 1),
    seccion('Llamada', llamada, 1),
    seccion('Toque Iyesá I', toque_1, 4),
    seccion('Variación de repique (Iyesá I)', variacion_1, 2),
    seccion('Llamada para Iyesá II', llamada_2, 1),
    seccion('Toque Iyesá II', toque_2, 4),
    seccion('Llamada para Iyesá III A', llamada_3a, 1),
    seccion('Toque Iyesá III A', toque_3a, 4),
    seccion('Llamada para Iyesá III B', llamada_3b, 1),
    seccion('Toque Iyesá III B', toque_3b, 4),
    seccion('Llamada para Iyesá III C', llamada_3c, 1),
    seccion('Iyesá III C', toque_3c, 4),
    seccion('Llamada final', llamada_final, 1),
])
