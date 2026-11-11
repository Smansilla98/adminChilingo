import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from fastapi.testclient import TestClient  # noqa: E402

from app import main  # noqa: E402


def cliente(monkeypatch):
    monkeypatch.setenv("TRANSCRIPTOR_SECRETO", "secreto-de-prueba")
    return TestClient(main.app)


def test_firma_invalida_es_401(monkeypatch):
    c = cliente(monkeypatch)
    cuerpo = json.dumps({"audio_url": "https://a/x", "callback_url": "https://a/cb", "job_id": "1"})
    assert c.post("/transcribir", content=cuerpo).status_code == 401
    assert c.post("/transcribir", content=cuerpo, headers={main.CABECERA: "sha256=mal"}).status_code == 401


def test_sin_secreto_no_acepta_nada(monkeypatch):
    monkeypatch.setenv("TRANSCRIPTOR_SECRETO", "")
    c = TestClient(main.app)
    cuerpo = b'{"job_id":"1"}'
    assert c.post("/transcribir", content=cuerpo, headers={main.CABECERA: main.firmar(cuerpo)}).status_code == 401


def test_pedido_firmado_es_202_y_procesa(monkeypatch):
    c = cliente(monkeypatch)
    llamados = []
    monkeypatch.setattr(main, "procesar", lambda p: llamados.append(p.job_id))
    cuerpo = json.dumps({"audio_url": "https://chilinga.test/a", "callback_url": "https://chilinga.test/cb", "job_id": "7"}).encode()
    r = c.post("/transcribir", content=cuerpo, headers={main.CABECERA: main.firmar(cuerpo)})
    assert r.status_code == 202 and llamados == ["7"]


def test_hosts_permitidos(monkeypatch):
    c = cliente(monkeypatch)
    monkeypatch.setenv("TRANSCRIPTOR_HOSTS_PERMITIDOS", "chilinga.test")
    cuerpo = json.dumps({"audio_url": "https://169.254.169.254/x", "callback_url": "https://chilinga.test/cb", "job_id": "8"}).encode()
    assert c.post("/transcribir", content=cuerpo, headers={main.CABECERA: main.firmar(cuerpo)}).status_code == 422


def test_firma_compatible_con_laravel(monkeypatch):
    """Misma firma que TranscriptorClient::firmar en PHP: sha256= + hex(HMAC-SHA256)."""
    monkeypatch.setenv("TRANSCRIPTOR_SECRETO", "secreto-de-prueba")
    assert main.firmar(b'{"job_id":1}') == "sha256=" + __import__("hmac").new(b"secreto-de-prueba", b'{"job_id":1}', "sha256").hexdigest()
