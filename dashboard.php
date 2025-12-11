<?php
// dashboard.php - Upgraded final dashboard with Roadmap, Resources, Focus Timer, Insights, Daily AI Tip
session_start();
include 'includes/config.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$user_id = $_SESSION['user_id'];

// helper: safe check table exists
function table_exists($conn, $table) {
  $t = $conn->real_escape_string($table);
  $res = $conn->query("SHOW TABLES LIKE '$t'");
  return $res && $res->num_rows > 0;
}

// function to log progress (used only if table exists)
function logSkillProgress($conn, $user_id) {
  if (!table_exists($conn, 'skill_progress')) return;
  $result = $conn->prepare("SELECT AVG(proficiency) AS avg_score FROM skills WHERE user_id=?");
  $result->bind_param("i", $user_id);
  $result->execute();
  $avg = round($result->get_result()->fetch_assoc()['avg_score'] ?? 0);
  $ins = $conn->prepare("INSERT INTO skill_progress (user_id, average_score) VALUES (?, ?)");
  $ins->bind_param("ii", $user_id, $avg);
  $ins->execute();
}

// ----------------- Handle Add / Delete Skills -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skill_name']) && !isset($_POST['delete_skill'])) {
  $skill = trim($_POST['skill_name']);
  $prof = intval($_POST['proficiency']);
  if ($skill !== '') {
    $ins = $conn->prepare("INSERT INTO skills (user_id, skill_name, proficiency) VALUES (?, ?, ?)");
    $ins->bind_param("isi", $user_id, $skill, $prof);
    $ins->execute();
  }
  logSkillProgress($conn, $user_id);
  header("Location: dashboard.php");
  exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_skill'])) {
  $del_id = intval($_POST['delete_skill']);
  $d = $conn->prepare("DELETE FROM skills WHERE id = ? AND user_id = ?");
  $d->bind_param("ii", $del_id, $user_id);
  $d->execute();
  logSkillProgress($conn, $user_id);
  header("Location: dashboard.php");
  exit();
}

// ----------------- Fetch user & skill data -----------------
$q = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE id=? LIMIT 1");
$q->bind_param("i", $user_id);
$q->execute();
$user = $q->get_result()->fetch_assoc() ?: ['name'=>'User','email'=>'','created_at'=>null];

// fetch skills
$skills = [];
$sq = $conn->prepare("SELECT id, skill_name, proficiency FROM skills WHERE user_id=? ORDER BY id DESC");
$sq->bind_param("i", $user_id);
$sq->execute();
$res = $sq->get_result();
while ($row = $res->fetch_assoc()) $skills[] = $row;

// fetch skill_progress only if table exists (prevent fatal errors)
$progress = [];
if (table_exists($conn, 'skill_progress')) {
  $pq = $conn->prepare("SELECT average_score, recorded_at FROM skill_progress WHERE user_id=? ORDER BY recorded_at ASC");
  $pq->bind_param("i", $user_id);
  $pq->execute();
  $r2 = $pq->get_result();
  while ($row = $r2->fetch_assoc()) $progress[] = $row;
}

// Helper: compute top skill for "Today's Focus"
$topSkill = null;
if (!empty($skills)) {
  $topSkill = $skills[0];
  foreach ($skills as $s) if ($s['proficiency'] > $topSkill['proficiency']) $topSkill = $s;
}

// ----------------- Roadmap & Resources mapping (simple, local) -----------------
// keys in lowercase -> roadmap steps and list of curated resources
$resource_map = [
  'python' => [
    'roadmap' => [
      'Basics & Syntax', 'Data Structures & Algorithms (in Python)', 'Small Projects (CLI & scripts)', 'Web framework (Flask)', 'Data Analysis (Pandas) & Mini Projects', 'Prepare portfolio + GitHub'
    ],
    'resources' => [
      ['title'=>'Official Python Tutorial', 'url'=>'https://docs.python.org/3/tutorial/'],
      ['title'=>'W3Schools Python', 'url'=>'https://www.w3schools.com/python/'],
      ['title'=>'Kaggle: Python & Data', 'url'=>'https://www.kaggle.com/learn/python']
    ]
  ],
  'machine learning' => [
    'roadmap' => [
      'Linear Algebra & Statistics basics', 'Python for ML (NumPy, Pandas)', 'Intro to ML (scikit-learn)', 'Deep Learning basics', 'Projects (classification, regression)', 'Model deployment basics'
    ],
    'resources' => [
      ['title'=>'Coursera: ML by Andrew Ng', 'url'=>'https://www.coursera.org/learn/machine-learning'],
      ['title'=>'fast.ai Practical Deep Learning', 'url'=>'https://www.fast.ai/'],
      ['title'=>'Kaggle Learn', 'url'=>'https://www.kaggle.com/learn/overview']
    ]
  ],
  'java' => [
    'roadmap' => ['Java Basics','OOP & Collections','Database (JDBC)','Build Projects (Spring Boot)','APIs & Microservices','Deployment & Portfolio'],
    'resources' => [
      ['title'=>'Oracle Java Tutorials', 'url'=>'https://docs.oracle.com/javase/tutorial/'],
      ['title'=>'Baeldung Java Guides', 'url'=>'https://www.baeldung.com/']
    ]
  ],
  'web' => [
    'roadmap' => ['HTML & CSS','JavaScript basics','Responsive design','Frontend framework (React)','Backend basics (Node/PHP)','Full stack project'],
    'resources' => [
      ['title'=>'MDN Web Docs', 'url'=>'https://developer.mozilla.org/'],
      ['title'=>'freeCodeCamp', 'url'=>'https://www.freecodecamp.org/']
    ]
  ],
  'react' => [
    'roadmap' => ['JS Fundamentals','React Basics','State & Hooks','Routing & APIs','Full-stack project (React + Backend)','Deploy & Portfolio'],
    'resources' => [
      ['title'=>'React Official', 'url'=>'https://reactjs.org/'],
      ['title'=>'Scrimba React Course', 'url'=>'https://scrimba.com/learn/learnreact']
    ]
  ],
  // default fallback
  'default' => [
    'roadmap' => ['Strengthen fundamentals','Build a small project','Document work on GitHub','Apply to internships','Iterate & learn new topics'],
    'resources' => [
      ['title'=>'freeCodeCamp', 'url'=>'https://www.freecodecamp.org/'],
      ['title'=>'Coursera', 'url'=>'https://www.coursera.org/']
    ]
  ]
];

// choose mapping key by looking into topSkill; else default
$map_key = 'default';
if ($topSkill) {
  $ts = strtolower($topSkill['skill_name']);
  foreach ($resource_map as $key => $val) {
    if ($key !== 'default' && strpos($ts, $key) !== false) { $map_key = $key; break; }
  }
  if ($map_key === 'default') {
    // try partial matches (python, ml, machine)
    if (strpos($ts, 'python') !== false) $map_key = 'python';
    elseif (strpos($ts, 'ml') !== false || strpos($ts, 'machine') !== false) $map_key = 'machine learning';
    elseif (strpos($ts, 'java') !== false) $map_key = 'java';
    elseif (strpos($ts, 'react') !== false) $map_key = 'react';
    elseif (strpos($ts, 'web') !== false || strpos($ts, 'html')!==false) $map_key = 'web';
  }
}

// daily AI tip array (small)
$ai_tips = [
  "Add a one-line summary at top of your resume that highlights your strongest skill and a key result.",
  "Push a small project to GitHub and link it in your resume—practical proof matters more than claims.",
  "Practice coding daily — 30 minutes of consistent practice beats long but infrequent sessions.",
  "Document learning: a short README for each project makes it easier for recruiters to evaluate your work.",
  "When applying, tailor your resume keywords to the job description (skills, frameworks)."
];

// pick a daily tip deterministically
$tipIndex = (int)date('j') % count($ai_tips);
$daily_tip = $ai_tips[$tipIndex];

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SkillTracker — Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* theme & base (kept your style) */
:root{
  --accent: #00ffe0;
  --accent2: #00b3ff;
  --bg1: #071017;
  --bg2: #021018;
  --glass: rgba(255,255,255,0.06);
  --muted: rgba(255,255,255,0.75);
  --text: #e9fbf8;
  --radius: 14px;
}
html,body{height:100%;margin:0}
body { font-family:'Poppins',sans-serif; margin:0; color:var(--text); background: radial-gradient(circle at 10% 10%, #04202a 0%, #001017 45%, #00060a 100%); -webkit-font-smoothing:antialiased; overflow:hidden; }
body.light-mode { background: linear-gradient(180deg,#f6fbff,#eef8ff); color:#222; }

/* layout */
.app { display:flex; height:100vh; gap:20px; padding:18px; box-sizing:border-box; }
.sidebar { width:96px; min-width:96px; background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); border-radius:18px; padding:12px 10px; display:flex; flex-direction:column; gap:10px; align-items:center; box-shadow: 0 10px 40px rgba(0,0,0,0.6); transition:width .28s; position:relative; z-index:30; }
.sidebar:hover { width:230px; }
.brand { color:var(--accent); font-weight:700; font-size:1.05rem; width:100%; text-align:center; padding:8px 6px; letter-spacing:0.6px; text-shadow: 0 0 18px rgba(0,255,224,0.18); }
.nav { display:flex; flex-direction:column; gap:8px; width:100%; align-items:stretch; }
.nav-btn { display:flex; align-items:center; gap:12px; padding:10px; border-radius:10px; color:var(--muted); background:transparent; border:none; cursor:pointer; text-align:left; width:100%; transition:all .18s; }
.nav-btn i { font-size:18px; width:28px; text-align:center; }
.nav-label { display:none; font-weight:600; white-space:nowrap; }
.sidebar:hover .nav-label { display:inline-block; }
.nav-btn.active { background: rgba(255,255,255,0.04); color:#fff; border-left:4px solid var(--accent); }

/* main */
.main { flex:1; height:100vh; overflow:auto; padding:18px; transition:margin-left .28s; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.welcome-text h2 { color:var(--accent); font-size:1.18rem; margin:0; }
.welcome-text p { opacity:.85; margin:0; font-size:0.95rem; }

/* avatar */
.avatar-wrap { position:relative; width:60px; height:60px; cursor:pointer; }
.avatar-img { width:60px; height:60px; border-radius:14px; object-fit:cover; border:2px solid rgba(0,255,224,0.12); transition:transform .25s; }
.avatar-img:hover { transform:scale(1.04); }

/* grid & card */
.grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:18px; align-items:start; }
.card { background:var(--glass); border-radius:var(--radius); padding:18px; box-shadow:0 12px 40px rgba(0,0,0,0.45); backdrop-filter:blur(12px); transition:transform .28s; }
.card:hover { transform: translateY(-8px) scale(1.01); box-shadow:0 26px 80px rgba(0,255,224,0.06); }
.card h2 { color:var(--accent); margin:0 0 10px 0; }

/* smaller motivation */
.mot { display:flex; flex-direction:column; align-items:center; justify-content:center; height:96px; gap:6px; text-align:center; padding:14px; border-left:4px solid var(--accent); background: linear-gradient(135deg, rgba(0,255,224,0.04), rgba(0,179,255,0.02)); border-radius:12px; }
.mot h2 { font-size:1.05rem; margin-bottom:6px; }
.mot p { margin:0; font-style:italic; color: rgba(255,255,255,0.9); }

/* focus */
.focus { padding:12px; border-radius:12px; background: linear-gradient(90deg, rgba(0,255,224,0.03), rgba(0,179,255,0.02)); box-shadow:0 8px 40px rgba(0,255,224,0.03); }

/* skills */
.skill-bar { height:10px; background: rgba(255,255,255,0.06); border-radius:10px; margin-top:6px; overflow:hidden; }
.skill-fill { height:100%; background: linear-gradient(90deg, var(--accent), var(--accent2)); }

/* jobs */
.job { display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); padding:12px; border-radius:10px; margin-bottom:12px; }
.job-logo { width:46px; height:46px; border-radius:8px; overflow:hidden; background:rgba(255,255,255,0.02); display:flex; align-items:center; justify-content:center; }
.job img { width:100%; height:100%; object-fit:contain; }

/* ai-box */
.ai-box { height:220px; overflow:auto; background:rgba(255,255,255,0.03); padding:10px; border-radius:10px; }

/* roadmap and resources style */
.roadmap-list { list-style: none; padding-left:0; margin:0; }
.roadmap-list li { padding:8px 10px; border-radius:8px; background: rgba(255,255,255,0.02); margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.resource-list { list-style:none; padding-left:0; margin:0; }
.resource-list li { margin-bottom:8px; }

/* timer */
.timer { display:flex; gap:12px; align-items:center; }
.timer-display { font-size:1.6rem; font-weight:700; padding:10px 16px; border-radius:10px; background:rgba(255,255,255,0.02); }

/* tips */
.tip { background: linear-gradient(90deg, rgba(0,255,224,0.03), rgba(0,179,255,0.02)); padding:12px; border-radius:10px; }

/* inputs & buttons */
.input, input[type="text"], input[type="number"], textarea { padding:10px; border-radius:10px; border:none; background: rgba(255,255,255,0.03); color:inherit; }
.btn { background: linear-gradient(90deg, var(--accent), var(--accent2)); padding:9px 12px; border-radius:10px; border:none; color:#001; font-weight:700; cursor:pointer; }

/* growth summary */
.growth-summary { color:var(--muted); margin-top:8px; }

/* profile modal */
.profile-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px); z-index:120; }
.profile-modal.active { display:flex; }
.popup-card { width:360px; background: linear-gradient(180deg, rgba(255,255,255,0.035), rgba(255,255,255,0.02)); border-radius:12px; padding:18px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.6); transform-style:preserve-3d; }
.popup-card img { width:84px; height:84px; border-radius:50%; border:3px solid var(--accent); margin-bottom:10px; }

/* responsive */
@media (max-width:900px) {
  .sidebar { position:static; width:100%; min-width:100%; display:flex; flex-direction:row; gap:8px; overflow:auto; border-radius:12px; padding:8px; }
  .sidebar:hover { width:100%; }
  .nav-label { display:none !important; }
  .main { padding:12px; }
  .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" aria-label="Sidebar">
    <div class="brand" title="SkillTracker">SkillTracker</div>

    <div class="nav" role="navigation" aria-label="Main">
      <button class="nav-btn active" onclick="navClick('motivation', this)"><i>💫</i><span class="nav-label">Motivation</span></button>
      <button class="nav-btn" onclick="navClick('focus', this)"><i>🎯</i><span class="nav-label">Today's Focus</span></button>
      <button class="nav-btn" onclick="navClick('skills', this)"><i>🧠</i><span class="nav-label">Skills</span></button>
      <button class="nav-btn" onclick="navClick('jobs', this)"><i>🎯</i><span class="nav-label">Jobs</span></button>
      <button class="nav-btn" onclick="navClick('career', this)"><i>🚀</i><span class="nav-label">Career</span></button>
      <button class="nav-btn" onclick="navClick('resume', this)"><i>📝</i><span class="nav-label">Resume</span></button>
      <button class="nav-btn" onclick="navClick('ai', this)"><i>🤖</i><span class="nav-label">AI Chat</span></button>
    </div>

    <div style="flex:1"></div>

    <div class="sidebar-bottom" style="width:100%;display:flex;justify-content:center;">
      <button class="nav-btn" id="themeBtn" onclick="toggleTheme()"><i id="themeIcon">🌙</i><span class="nav-label">Theme</span></button>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main" id="mainArea">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="welcome-text">
        <h2>Welcome back, <?php echo htmlspecialchars($user['name']); ?> 👋</h2>
        <p>Your AI-powered career coach and skill tracker.</p>
      </div>
      <div class="avatar-wrap" title="Open profile" onclick="toggleProfilePopup()">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=00ffe0&color=000&bold=true" alt="avatar" class="avatar-img" id="topAvatar">
      </div>
    </div>

    <!-- DASHBOARD GRID -->
    <div class="grid">

      <!-- MOTIVATION (smaller, positive) -->
      <article class="card mot" id="motivation">
        <div>
          <h2>💫 Daily Motivation</h2>
          <p id="quoteBox">Loading motivational quote...</p>
        </div>
      </article>

      <!-- TODAY'S FOCUS -->
      <article class="card focus" id="focus" aria-live="polite">
        <h2>🎯 Today’s Focus</h2>
        <div id="focusText">
          <?php
            if ($topSkill) {
              $skillName = htmlspecialchars($topSkill['skill_name']);
              $prof = intval($topSkill['proficiency']);
              if ($prof < 40) {
                echo "<p>Focus on fundamentals of <strong>$skillName</strong> today — try a 30–60 minute hands-on task.</p>";
              } elseif ($prof < 70) {
                echo "<p>Build a small project using <strong>$skillName</strong> to deepen understanding and create portfolio material.</p>";
              } else {
                echo "<p>Polish advanced topics in <strong>$skillName</strong> and write a short README for your project.</p>";
              }
            } else {
              echo "<p>Add your first skill — I'll suggest a focused task every day based on your strengths.</p>";
            }
          ?>
        </div>
      </article>

      <!-- SKILLS -->
      <article class="card" id="skills">
        <h2>🧠 My Skills</h2>
        <form method="POST" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
          <input name="skill_name" placeholder="Skill (e.g. Python)" style="flex:1;min-width:160px" required>
          <input name="proficiency" type="number" placeholder="%" min="0" max="100" style="width:110px" required>
          <button class="btn" type="submit">Add</button>
        </form>

        <?php if (empty($skills)): ?>
          <p style="opacity:0.9">You have no skills yet — add your first skill to get personalized guidance and job matches.</p>
        <?php else: ?>
          <?php foreach ($skills as $s): ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <strong><?php echo htmlspecialchars($s['skill_name']); ?></strong>
                <small style="opacity:.8"><?php echo intval($s['proficiency']); ?>%</small>
              </div>
              <div class="skill-bar"><div class="skill-fill" style="width:<?php echo intval($s['proficiency']); ?>%"></div></div>
              <div style="display:flex;justify-content:flex-end;margin-top:8px">
                <form method="POST" onsubmit="return confirm('Delete this skill permanently?')">
                  <input type="hidden" name="delete_skill" value="<?php echo $s['id']; ?>">
                  <button type="submit" class="btn" style="background:#ff6b6b;color:#fff;padding:6px 10px">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top:14px">
          <canvas id="skillChart" height="160"></canvas>
        </div>
      </article>

      <!-- JOBS -->
      <article class="card" id="jobs">
        <h2>🎯 Job Recommendations</h2>
        <?php
        if (!empty($skills)) {
          $topSkills = array_slice($skills,0,3);
          $jobsList = [];
          foreach ($topSkills as $s) {
            $sk = strtolower($s['skill_name']);
            if (strpos($sk,'python') !== false) {
              $jobsList[] = ['Python Developer','Google','Bangalore','https://careers.google.com','google.com'];
              $jobsList[] = ['Backend Engineer (Python)','TCS','Hyderabad','https://in.linkedin.com/jobs','tcs.com'];
            } elseif (strpos($sk,'machine')!==false || strpos($sk,'ml')!==false) {
              $jobsList[] = ['Machine Learning Intern','Google','Pune','https://careers.google.com','google.com'];
              $jobsList[] = ['AI Research Engineer','Microsoft','Remote','https://careers.microsoft.com','microsoft.com'];
            } elseif (strpos($sk,'java')!==false) {
              $jobsList[] = ['Java Backend Developer','Accenture','Chennai','https://www.naukri.com/java-developer-jobs','accenture.com'];
              $jobsList[] = ['Spring Boot Developer','Cognizant','Bangalore','https://in.linkedin.com/jobs','cognizant.com'];
            } elseif (strpos($sk,'web')!==false || strpos($sk,'react')!==false) {
              $jobsList[] = ['Frontend Developer','Zoho','Chennai','https://careers.zoho.com','zoho.com'];
              $jobsList[] = ['Full Stack Developer','Wipro','Mumbai','https://careers.wipro.com','wipro.com'];
            } else {
              $jobsList[] = ['Software Engineer','Tech Mahindra','Hyderabad','https://careers.techmahindra.com','techmahindra.com'];
            }
          }
          // unique by join
          $unique = [];
          foreach ($jobsList as $j) $unique[implode('|',$j)] = $j;
          foreach ($unique as $job) {
            $logo = "https://logo.clearbit.com/{$job[4]}";
            echo "<div class='job'>
                    <div style='display:flex;gap:12px;align-items:center'>
                      <div class='job-logo'><img src='{$logo}' alt='{$job[1]}' onerror=\"this.src='https://via.placeholder.com/46?text=?'\" style='width:100%;height:100%;object-fit:contain'></div>
                      <div>
                        <div style='font-weight:600'>{$job[0]}</div>
                        <div style='font-size:0.9rem;opacity:0.85'>{$job[1]} — {$job[2]}</div>
                      </div>
                    </div>
                    <div><a href='{$job[3]}' target='_blank'><button class='btn'>Apply</button></a></div>
                  </div>";
          }
        } else {
          echo "<p style='opacity:0.9'>Add skills to get job recommendations tailored to you.</p>";
        }
        ?>
      </article>

      <!-- CAREER PATH -->
      <article class="card" id="career">
        <h2>🚀 AI Career Path Generator</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input id="careerGoal" placeholder="e.g., Data Scientist at Google" style="flex:1;min-width:200px" class="input">
          <button class="btn" onclick="generateCareerPath()">Generate</button>
        </div>
        <div id="careerResult" style="margin-top:12px;color:var(--accent)"></div>
      </article>

      <!-- RESUME REVIEW -->
      <article class="card" id="resume">
        <h2>📝 Resume Reviewer</h2>
        <p style="opacity:0.9">Upload your resume and get instant AI-driven feedback.</p>
        <div style="margin-top:10px">
          <a class="btn" href="resume_review.php">Open Resume Reviewer</a>
        </div>
      </article>

      <!-- AI CHAT -->
      <article class="card" id="ai">
        <h2>🤖 Ask SkillTracker AI</h2>
        <div class="ai-box" id="chatWindow">
          <div class="ai-msg"><strong>SkillTracker AI:</strong> Hi <?php echo htmlspecialchars($user['name']); ?> — ask me anything about skills, projects, or jobs.</div>
        </div>
        <div style="display:flex;gap:8px">
          <input id="aiInput" class="input" placeholder="e.g., How to prepare for ML interviews?">
          <button class="btn" onclick="askAI()">Send</button>
        </div>
      </article>

      <!-- NEW: Learning Roadmap -->
      <article class="card" id="roadmap">
        <h2>🗺️ Learning Roadmap</h2>
        <p style="opacity:0.9">A practical sequence of steps tailored to your top skill: <strong><?php echo $topSkill ? htmlspecialchars($topSkill['skill_name']) : '—'; ?></strong></p>
        <ul class="roadmap-list" id="roadmapList">
          <?php
            $map = $resource_map[$map_key] ?? $resource_map['default'];
            foreach ($map['roadmap'] as $idx => $step) {
              echo "<li><span>".($idx+1).".</span><span style='opacity:.9'>{$step}</span></li>";
            }
          ?>
        </ul>
      </article>

      <!-- NEW: Courses & Resources -->
      <article class="card" id="resources">
        <h2>📚 Courses & Resources</h2>
        <p style="opacity:0.9">Curated resources for <strong><?php echo $topSkill ? htmlspecialchars($topSkill['skill_name']) : 'your top skill'; ?></strong></p>
        <ul class="resource-list">
          <?php
            foreach ($map['resources'] as $r) {
              $t = htmlspecialchars($r['title']);
              $u = htmlspecialchars($r['url']);
              echo "<li><a href='{$u}' target='_blank' style='color:var(--accent);text-decoration:none;font-weight:600'>{$t}</a> <span style='color:var(--muted);display:block;font-size:.9rem'>{$u}</span></li>";
            }
          ?>
        </ul>
      </article>

      <!-- NEW: Focus Timer (Pomodoro) -->
      <article class="card" id="timerCard">
        <h2>⏱️ Focus Mode — Study Timer</h2>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
          <div class="timer">
            <div class="timer-display" id="timerDisplay">25:00</div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="startTimer()">Start</button>
                <button class="btn" onclick="pauseTimer()">Pause</button>
                <button class="btn" onclick="resetTimer()">Reset</button>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="setTimer(25)">25m</button>
                <button class="btn" onclick="setTimer(50)">50m</button>
                <button class="btn" onclick="setTimer(15)">15m</button>
              </div>
            </div>
          </div>
        </div>
        <p style="opacity:0.85;margin-top:10px">Tip: Use Focus Mode to complete short project tasks from your roadmap (Pomodoro technique).</p>
      </article>

      <!-- NEW: Skill Growth Insights -->
      <article class="card" id="insights">
        <h2>🔎 Skill Growth Insights</h2>
        <?php
          // compute simple stats
          $totalSkills = count($skills);
          $avg = 0;
          $topName = '-';
          $topVal = 0;
          if ($totalSkills) {
            $sum = array_sum(array_map(function($s){ return intval($s['proficiency']); }, $skills));
            $avg = round($sum / $totalSkills, 1);
            // find top skill
            $best = $skills[0];
            foreach ($skills as $s) if ($s['proficiency'] > $best['proficiency']) $best = $s;
            $topName = htmlspecialchars($best['skill_name']);
            $topVal = intval($best['proficiency']);
          }
        ?>
        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
          <div style="min-width:160px">
            <div style="font-size:1.6rem;font-weight:700;color:var(--accent)"><?php echo $avg; ?>%</div>
            <div style="opacity:.85">Average proficiency across <?php echo $totalSkills; ?> skills</div>
          </div>
          <div>
            <div style="font-weight:700"><?php echo $topName; ?></div>
            <div style="opacity:.85">Top skill — <?php echo $topVal; ?>%</div>
          </div>
        </div>
        <div class="growth-summary" id="insightText" style="margin-top:12px">
          <?php
            // small heuristic insight
            if (empty($skills)) echo "Add skills to get meaningful insights and personalized advice.";
            else {
              if ($avg >= 75) echo "Great progress — your overall proficiency is strong. Focus now on projects to demonstrate applied skills.";
              elseif ($avg >= 50) echo "Good progress — try one project per top skill to showcase applied learning.";
              else echo "Start with fundamentals and small projects; 30 minutes daily can produce strong progress in weeks.";
            }
          ?>
        </div>
      </article>

      <!-- Skill Growth Tracker (existing) -->
      <article class="card" id="growth">
        <h2>📈 Skill Growth Tracker</h2>
        <canvas id="growthChart" height="140"></canvas>
        <div id="growthSummary" style="margin-top:10px;color:var(--muted)"></div>
      </article>

      <!-- DAILY AI TIP -->
      <article class="card" id="aiTip">
        <h2>💡 Daily AI Tip</h2>
        <div class="tip"><?php echo htmlspecialchars($daily_tip); ?></div>
      </article>

    </div> <!-- end grid -->

    <footer>© 2025 SkillTracker — AI-Powered Career Guidance</footer>
  </main>
</div>

<!-- PROFILE POPUP -->
<div class="profile-modal" id="profilePopup" onclick="if(event.target===this) toggleProfilePopup()">
  <div class="popup-card">
    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=00ffe0&color=000&bold=true" alt="avatar">
    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
    <p style="opacity:.9"><?php echo htmlspecialchars($user['email']); ?></p>
    <p style="opacity:.8;font-size:.9rem">Joined: <?php echo isset($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—'; ?></p>
    <div style="margin-top:12px;display:flex;gap:8px;flex-direction:column">
      <a href="edit_profile.php"><button class="btn">Edit Profile</button></a>
      <button class="btn" onclick="toggleProfilePopup()">Close</button>
      <button class="btn" style="background:#ff6b6b;color:#fff" onclick="window.location.href='logout.php'">Logout</button>
    </div>
  </div>
</div>

<script>
/* ---------- Theme toggle (persist) ---------- */
(function applySavedTheme(){
  const saved = localStorage.getItem('skilltracker_theme');
  if(saved === 'light') { document.body.classList.add('light-mode'); document.getElementById('themeIcon').innerText='🌞'; }
})();
function toggleTheme(){
  document.body.classList.toggle('light-mode');
  const mode = document.body.classList.contains('light-mode') ? 'light' : 'dark';
  localStorage.setItem('skilltracker_theme', mode);
  document.getElementById('themeIcon').innerText = mode === 'light' ? '🌞' : '🌙';
}

/* ---------- Nav click ---------- */
function navClick(section, el){
  document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('active'));
  if (el) el.classList.add('active');
  const node = document.getElementById(section);
  if(node) node.scrollIntoView({behavior:'smooth', block:'start'});
}

/* ---------- Profile popup ---------- */
function toggleProfilePopup(){ document.getElementById('profilePopup').classList.toggle('active'); }

/* ---------- Quotes ---------- */
fetch("assets/quotes.json").then(r=>r.json()).then(q=>{
  const i = (new Date()).getDate() % q.length;
  document.getElementById('quoteBox').innerText = q[i];
}).catch(()=>{ document.getElementById('quoteBox').innerText='Keep learning — one step at a time.'; });

/* ---------- Skill chart ---------- */
const ctx = document.getElementById('skillChart')?.getContext('2d');
const skillNames = <?php echo json_encode(array_column($skills,'skill_name')); ?>;
const skillVals = <?php echo json_encode(array_column($skills,'proficiency')); ?>;
if(ctx){
  new Chart(ctx, {
    type: 'bar',
    data: { labels: skillNames, datasets: [{ label: 'Proficiency %', data: skillVals, backgroundColor: 'rgba(0,255,224,0.4)', borderColor: '#00ffe0', borderWidth: 1 }] },
    options: { plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, max:100, ticks:{ color: document.body.classList.contains('light-mode') ? '#222' : '#fff' } }, x:{ ticks:{ color: document.body.classList.contains('light-mode') ? '#222' : '#fff' } } } }
  });
}

/* ---------- Growth chart (uses progress data if present) ---------- */
const progressData = <?php echo json_encode($progress); ?>;
if(progressData.length && document.getElementById('growthChart')){
  const ctx2 = document.getElementById('growthChart').getContext('2d');
  const labels = progressData.map(d => new Date(d.recorded_at).toLocaleDateString());
  const values = progressData.map(d => Number(d.average_score));
  new Chart(ctx2, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Average Skill Score',
        data: values,
        borderColor: 'rgba(0,255,224,0.95)',
        backgroundColor: 'rgba(0,255,224,0.12)',
        fill: true,
        tension: 0.25,
        pointRadius: 4,
      }]
    },
    options: { plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, max:100, ticks:{ color: document.body.classList.contains('light-mode') ? '#222' : '#fff' } }, x:{ ticks:{ color: document.body.classList.contains('light-mode') ? '#222' : '#fff' } } } }
  });

  const summary = document.getElementById('growthSummary');
  const first = values[0], last = values[values.length-1];
  const diff = (last - first).toFixed(1);
  if (diff > 0) summary.innerHTML = `🔥 You've improved your average skill by <b>${diff}%</b> since you started. Keep going!`;
  else if (diff < 0) summary.innerHTML = `⚡ Your average dipped by ${Math.abs(diff)}% — consider a short focused review.`;
  else summary.innerHTML = `⚡ Your average is steady — keep adding skills and projects!`;
} else {
  const gs = document.getElementById('growthSummary');
  if (gs) gs.innerText = 'Add skills to start tracking your growth!';
}

/* ---------- Career Path (AJAX) ---------- */
async function generateCareerPath(){
  const goal = document.getElementById('careerGoal').value.trim();
  const box = document.getElementById('careerResult');
  if(!goal){ box.innerText = 'Please enter a clear career goal.'; return; }
  box.innerText = '🧠 Generating roadmap...';
  try {
    const res = await fetch('career_path.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({goal})});
    const d = await res.json();
    box.innerHTML = d.plan || d.message || 'No plan returned.';
  } catch(e) { box.innerText = 'AI unavailable. Try again later.'; }
}

/* ---------- AI Chat (AJAX) ---------- */
async function askAI(){
  const txt = document.getElementById('aiInput').value.trim();
  if(!txt) return;
  const chat = document.getElementById('chatWindow');
  chat.innerHTML += `<div style="margin:8px 0"><strong>You:</strong> ${escapeHtml(txt)}</div>`;
  document.getElementById('aiInput').value = '';
  const typing = document.createElement('div'); typing.innerHTML = '<strong>SkillTracker AI:</strong> typing...'; chat.appendChild(typing); chat.scrollTop = chat.scrollHeight;
  try {
    const res = await fetch('ai_backend.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:txt})});
    const d = await res.json();
    typing.innerHTML = `<strong>SkillTracker AI:</strong> ${escapeHtml(d.response || d.answer || 'No response')}`;
  } catch(e) { typing.innerHTML = '<strong>SkillTracker AI:</strong> Service unavailable.'; }
}

/* ---------- Helpers ---------- */
function escapeHtml(s){ return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }

/* ---------- Focus Timer (simple JS) ---------- */
let timerSeconds = 25 * 60;
let timerInterval = null;
let initialSeconds = timerSeconds;

function formatTime(sec) {
  const m = Math.floor(sec / 60).toString().padStart(2,'0');
  const s = (sec % 60).toString().padStart(2,'0');
  return `${m}:${s}`;
}
function setTimer(mins) {
  initialSeconds = mins * 60;
  timerSeconds = initialSeconds;
  document.getElementById('timerDisplay').innerText = formatTime(timerSeconds);
}
function startTimer(){
  if(timerInterval) return; // already running
  timerInterval = setInterval(()=>{
    if (timerSeconds <= 0) { clearInterval(timerInterval); timerInterval = null; alert('Focus session finished! Take a short break.'); return; }
    timerSeconds--;
    document.getElementById('timerDisplay').innerText = formatTime(timerSeconds);
  }, 1000);
}
function pauseTimer(){
  if(timerInterval){ clearInterval(timerInterval); timerInterval = null; }
}
function resetTimer(){
  pauseTimer();
  timerSeconds = initialSeconds;
  document.getElementById('timerDisplay').innerText = formatTime(timerSeconds);
}
// set default 25 min on load
document.addEventListener('DOMContentLoaded', ()=>{ setTimer(25); });

/* ---------- accessible focus on load ---------- */
document.addEventListener('DOMContentLoaded', ()=> {
  // small entrance for cards
  document.querySelectorAll('.card').forEach((c,i)=>{ c.style.opacity=0; setTimeout(()=>{ c.style.opacity=1; c.style.transform='none'; }, 80*i); });
});
</script>
</body>
</html>
