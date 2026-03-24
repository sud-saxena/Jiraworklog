# Jiraworklog
A lightweight PHP-based utility to fetch, display, and export Jira worklogs for a given date range. This tool integrates with the Jira REST API to generate detailed worklog reports with filtering, caching, and export capabilities.

---
🚀 Features
---

- 🔍 Fetch worklogs using Jira REST API (v3)
- 📅 Filter by date range
- 👤 Automatically fetch current user’s logs
- ⚡ Optimized performance with smart caching
- 📊 Tabular UI for easy viewing
- 📁 Export reports to CSV
- 🧹 Manual cache clearing
- 🛡️ Safe handling of API failures (no corrupted cache)

---
🛠️ Tech Stack
---
- PHP (Core)
- cURL (API requests)
- Jira REST API v3
- HTML/CSS (simple UI)
---

## 🐳 Docker Setup

This project supports Docker for easy local development and deployment.

---

### 🚀 Prerequisites

- Docker  
- Docker Compose  

---

### ⚙️ Setup Instructions

#### 1. Clone the repository

```bash
git clone https://github.com/<your-username>/jiraworklog.git
cd jiraworklog
```

#### 2. Create environment file

```bash
cp .env.example .env
```

#### 3. Update `.env` with your Jira credentials

```env
JIRA_BASE_URL=https://your-domain.atlassian.net
JIRA_EMAIL=your-email
JIRA_API_TOKEN=your-api-token
```

---

### ▶️ Run Application

```bash
docker-compose up -d --build
```

---

### 🌐 Access Application

```text
http://localhost:9090
```

---

### 🛑 Stop Application

```bash
docker-compose down
```

---

### 📜 View Logs

```bash
docker logs -f jiraworklog_app_1
```

---

### 🧹 Clear Cache (Manual)

```bash
rm cache_*.json
```

---

### ⚠️ Common Issues

#### Port already in use

```text
Bind for 0.0.0.0:9090 failed
```

Fix:

```bash
docker-compose down
```

Or change port in `docker-compose.yml`:

```yaml
ports:
  - "9091:80"
```

---

### 💡 Notes

- `.env` is automatically created from `.env.example` if missing  
- Cache files are stored locally (`cache_*.json`)  
- Uses Apache + PHP 8.1  

---

### 🔮 Future Improvements

- Multi-container setup (Nginx + PHP-FPM)  
- Redis caching  
- Production-ready Docker optimizations  