# Local Development Workflow

Start the environment:

```powershell
cd $env:USERPROFILE\Desktop\TESI-MOODLE
docker compose up -d
```

Deploy plugin changes:

```powershell
.\plugins\aiskillnavigator\scripts\deploy-plugin.ps1
```

Run lint:

```powershell
.\plugins\aiskillnavigator\scripts\lint-plugin.ps1
```

Open plugin:

```text
http://localhost:8080/local/aiskillnavigator/index.php
```