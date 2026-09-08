# Fuentes CC0 del kit de playback

Árbol mínimo que `scripts/build-chilinga-kit.py` necesita para regenerar
`public/sounds/perc/*.wav`. No es la batería grabada de La Chilinga.

- `vcsl/` — one-shots de [VCSL](https://github.com/sgossner/VCSL) (CC0, Versilian Studios LLC).
- `world-percussion-2020-09-05/` — recortes de [FreePats World Percussion](https://freepats.zenvoid.org/Percussion/world-and-rare-percussion.html) (CC0).

`start.sh` llama al builder si hay `python3` y `ffmpeg`. Para saltear:

```
PERC_KIT_REBUILD=0
```

A mano:

```
python3 scripts/build-chilinga-kit.py scripts/chilinga-kit-src
```
