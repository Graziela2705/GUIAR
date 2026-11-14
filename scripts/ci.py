import os
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
HOST = "127.0.0.1"
PORT = 8080
BASE_URL = f"http://{HOST}:{PORT}"


def require_command(name):
    command = shutil.which(name)
    if command is None:
        raise RuntimeError(f"Comando obrigatório não encontrado: {name}")
    return command


def wait_for_server(server):
    deadline = time.monotonic() + 15
    url = f"{BASE_URL}/index.html"

    while time.monotonic() < deadline:
        if server.poll() is not None:
            raise RuntimeError("O servidor PHP encerrou antes de ficar disponível.")

        try:
            with urllib.request.urlopen(url, timeout=1) as response:
                if response.status == 200:
                    return
        except urllib.error.HTTPError:
            raise
        except urllib.error.URLError:
            time.sleep(0.25)

    raise RuntimeError(f"O servidor PHP não respondeu em {BASE_URL} dentro do prazo.")


def run_frontend_tests():
    php = require_command("php")
    npm_name = "npm.cmd" if os.name == "nt" else "npm"
    npm = require_command(npm_name)
    server = subprocess.Popen(
        [php, "-S", f"{HOST}:{PORT}", "-t", str(ROOT)],
        cwd=ROOT,
    )

    try:
        wait_for_server(server)
        subprocess.run([npm, "run", "test:frontend"], cwd=ROOT, check=True)
    finally:
        if server.poll() is None:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()


def main():
    try:
        run_frontend_tests()
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Falha na pipeline de testes: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
