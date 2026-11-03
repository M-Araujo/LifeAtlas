"""Validate Compose only: no daemon, real .env, mounts or database access.

Run with: python tests/compose_backups_test.py
Requires Python 3 and Docker Compose v2.
"""

import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]


class ComposeBackupsTest(unittest.TestCase):
    def test_example_has_one_definition_of_each_backup_setting(self):
        example = (ROOT / ".env.example").read_text()
        self.assertEqual([""], re.findall(r"^BACKUP_HOST_PATH=(.*)$", example, re.MULTILINE))
        self.assertEqual(["host"], re.findall(r"^BACKUP_PERMISSION_MODE=(.*)$", example, re.MULTILINE))

    def render(self, setting="", resolve_paths=False):
        # Shell variables take precedence over --env-file. Remove every input
        # referenced by this Compose file, plus Compose's own environment knobs.
        variables = set(re.findall(r"\$\{([A-Za-z_][A-Za-z0-9_]*)", (ROOT / "compose.yaml").read_text()))
        environment = {
            key: value for key, value in os.environ.items()
            if key not in variables and not key.startswith("COMPOSE_")
        }
        with tempfile.TemporaryDirectory(prefix="lifeatlas-compose-test-") as directory:
            env_file = Path(directory) / "synthetic.env"
            env_file.write_text(
                "WWWUSER=1000\nWWWGROUP=1000\nPWD=/synthetic/project\n"
                "DB_PASSWORD=synthetic\nDB_DATABASE=synthetic\nDB_USERNAME=synthetic\n" + setting,
                encoding="utf-8",
            )
            command = [
                "docker", "compose", "--project-directory", str(ROOT),
                "--file", str(ROOT / "compose.yaml"),
                "--project-name", "lifeatlas-config-test", "--env-file", str(env_file),
                "config", "--format", "json",
            ]
            if not resolve_paths:
                command.append("--no-path-resolution")
            result = subprocess.run(command, env=environment, capture_output=True, text=True, check=True)
            return json.loads(result.stdout)

    def assert_mounts(self, config, source, mode="host"):
        for name in ("laravel.test", "queue"):
            service = config["services"][name]
            mount, = [item for item in service["volumes"] if item["target"] == "/var/lib/lifeatlas/backups"]
            self.assertEqual("bind", mount["type"])
            self.assertEqual(source, mount["source"])
            # Compose's JSON serializer omits false fields.
            self.assertFalse(mount["bind"].get("create_host_path", False))
            self.assertEqual("/var/lib/lifeatlas", service["environment"]["BACKUP_STORAGE_ROOT"])
            self.assertEqual(mode, service["environment"]["BACKUP_PERMISSION_MODE"])
        database = config["services"]["mariadb"]
        self.assertEqual("sail-mariadb", database["volumes"][0]["source"])
        self.assertEqual("/var/lib/mysql", database["volumes"][0]["target"])

    def test_unset_and_empty_use_default(self):
        for setting in ("", "BACKUP_HOST_PATH=\n"):
            with self.subTest(setting=setting):
                self.assert_mounts(self.render(setting), "../LifeAtlasData/backups")

    def test_custom_paths_and_permission_modes(self):
        for path, mode in (
            ("/srv/lifeatlas/backups", "posix"),
            ("/mnt/c/Users/example/LifeAtlasData/backups", "host"),
            ("C:/Users/example/LifeAtlasData/backups", "host"),
            ("/srv/backup data/backups", "host"),
        ):
            with self.subTest(path=path):
                self.assert_mounts(self.render(f'BACKUP_HOST_PATH="{path}"\nBACKUP_PERMISSION_MODE={mode}\n'), path, mode)

    def test_default_resolves_relative_to_project(self):
        config = self.render(resolve_paths=True)
        expected = (ROOT.parent / "LifeAtlasData" / "backups").as_posix()
        for name in ("laravel.test", "queue"):
            mount, = [item for item in config["services"][name]["volumes"] if item["target"] == "/var/lib/lifeatlas/backups"]
            self.assertEqual(expected.lower(), mount["source"].replace("\\", "/").lower())


if __name__ == "__main__":
    unittest.main(verbosity=2)
