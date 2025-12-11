<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkillTracker — Empowering Every BTech Student</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700;900&display=swap" rel="stylesheet">

<style>
:root {
  --bg1: #01060b;
  --bg2: #03202e;
  --accent: #00ffe0;
  --accent2: #009dff;
  --text: #ffffff;
  --card: rgba(255,255,255,0.05);
}

/* Reset & Base */
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family:'Poppins', sans-serif;
  color: var(--text);
  background: linear-gradient(135deg, var(--bg1), var(--bg2));
  overflow-x: hidden;
  scroll-behavior: smooth;
}

/* Navbar */
nav {
  position: fixed;
  top:0;
  width:100%;
  z-index:50;
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:20px 70px;
  background:rgba(0,0,0,0.25);
  backdrop-filter:blur(25px);
  border-bottom:1px solid rgba(255,255,255,0.08);
}
.logo {
  font-size:1.8rem;
  font-weight:800;
  color:var(--accent);
  text-shadow:0 0 25px rgba(0,255,224,0.5);
}
nav ul {
  list-style:none;
  display:flex;
  gap:25px;
}
nav a {
  color:rgba(255,255,255,0.85);
  text-decoration:none;
  font-weight:500;
  transition:color 0.3s;
}
nav a:hover { color:var(--accent); }
.theme-toggle {
  cursor:pointer;
  font-size:1.3rem;
  color:var(--accent);
  transition:transform 0.3s;
}
.theme-toggle:hover { transform:rotate(25deg); }

/* Hero */
.hero {
  height:100vh;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  text-align:center;
  padding:0 10%;
  position:relative;
}
.hero h1 {
  font-size:3.5rem;
  color:var(--accent);
  text-shadow:0 0 30px rgba(0,255,224,0.5);
  animation: glow 2.5s ease-in-out infinite alternate;
}
@keyframes glow {
  from { text-shadow:0 0 20px rgba(0,255,224,0.3); }
  to { text-shadow:0 0 60px rgba(0,255,224,1); }
}
.hero p {
  font-size:1.2rem;
  color:rgba(255,255,255,0.8);
  max-width:650px;
  margin-top:20px;
  line-height:1.7;
}
.btns {
  margin-top:30px;
  display:flex;
  gap:15px;
}
button {
  padding:12px 28px;
  border:none;
  border-radius:30px;
  font-weight:700;
  cursor:pointer;
  background:linear-gradient(90deg,var(--accent),var(--accent2));
  color:#000;
  transition:transform 0.3s, box-shadow 0.3s;
}
button:hover {
  transform:translateY(-3px);
  box-shadow:0 0 25px rgba(0,255,224,0.4);
}

/* About Section */
.about {
  padding:100px 10%;
  text-align:center;
}
.about h2 {
  font-size:2.2rem;
  color:var(--accent);
  margin-bottom:15px;
}
.about p {
  color:rgba(255,255,255,0.8);
  max-width:800px;
  margin:0 auto;
  line-height:1.8;
  font-size:1.05rem;
}

/* Features Section */
.features {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
  gap:25px;
  padding:80px 10%;
  text-align:center;
}
.card {
  background:var(--card);
  border-radius:16px;
  padding:25px;
  backdrop-filter:blur(10px);
  box-shadow:0 8px 35px rgba(0,0,0,0.4);
  transition:transform 0.3s ease;
}
.card:hover { transform:translateY(-10px) scale(1.03); }
.card h3 {
  color:var(--accent);
  margin-bottom:10px;
  font-size:1.3rem;
}
.card p {
  color:rgba(255,255,255,0.8);
  font-size:0.95rem;
  line-height:1.5;
}

/* Testimonials Section */
.testimonials {
  background:linear-gradient(180deg, rgba(0,0,0,0.2), rgba(0,0,0,0.4));
  padding:80px 10%;
  text-align:center;
}
.testimonials h2 {
  color:var(--accent);
  margin-bottom:25px;
  font-size:2rem;
}
.testimonial-box {
  display:flex;
  flex-wrap:wrap;
  gap:25px;
  justify-content:center;
}
.t-card {
  background:rgba(255,255,255,0.04);
  padding:25px;
  border-radius:14px;
  width:300px;
  box-shadow:0 8px 25px rgba(0,0,0,0.4);
}
.t-card p {
  font-size:0.95rem;
  color:rgba(255,255,255,0.8);
  margin-bottom:12px;
}
.t-card h4 {
  color:var(--accent2);
  font-weight:600;
}

/* Footer */
footer {
  text-align:center;
  padding:40px;
  color:rgba(255,255,255,0.6);
  font-size:0.9rem;
  background:rgba(0,0,0,0.2);
  border-top:1px solid rgba(255,255,255,0.05);
}

/* Light Mode */
.light-mode {
  --bg1: #f7fdff;
  --bg2: #e4f7fb;
  --text: #111;
  --card: #ffffff;
}
.light-mode .logo { color:#009fb8; }
.light-mode nav a { color:#333; }
.light-mode nav a:hover { color:#00a0c4; }
.light-mode .card h3 { color:#009fb8; }
.light-mode .t-card { background:#fff; color:#222; }
.light-mode footer { background:#f1f9fb; }
</style>
</head>
<body>

<!-- Navbar -->
<nav>
  <div class="logo">SkillTracker</div>
  <ul>
    <li><a href="#about">About</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#testimonials">Testimonials</a></li>
  </ul>
  <div class="theme-toggle" id="themeToggle">🌙</div>
</nav>

<!-- Hero -->
<section class="hero" id="about">
  <h1>Empowering Every Mind to Master Technology</h1>
  <p>SkillTracker is your AI-powered academic and career assistant.  
  Track your skills, prepare for interviews, and connect with job opportunities tailored to your profile.</p>
  <div class="btns">
    <button onclick="window.location.href='login.php'">Login</button>
    <button onclick="window.location.href='register.php'">Register</button>
  </div>
</section>

<!-- About -->
<section class="about">
  <h2>About SkillTracker</h2>
  <p>SkillTracker helps students monitor their skill development, stay motivated with AI-powered feedback, 
  and plan for their dream careers. The platform simplifies learning by connecting academic progress with career readiness.</p>
</section>

<!-- Features -->
<section class="features" id="features">
  <div class="card">
    <h3>🧠 AI Career Insights</h3>
    <p>Personalized suggestions based on your strengths, skills, and goals using AI analytics.</p>
  </div>
  <div class="card">
    <h3>🎯 Smart Job Matching</h3>
    <p>Instant job listings from MNCs like Google, TCS, and Microsoft based on your profile.</p>
  </div>
  <div class="card">
    <h3>📈 Skill Progress Tracker</h3>
    <p>Interactive charts to visualize your weekly learning progress and skill mastery.</p>
  </div>
  <div class="card">
    <h3>🤖 AI Chat Assistant</h3>
    <p>Ask questions about career paths, skills, or resume tips — all inside your dashboard.</p>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials" id="testimonials">
  <h2>What Students Say</h2>
  <div class="testimonial-box">
    <div class="t-card">
      <p>“SkillTracker helped me focus on the right technologies for my placement. The AI tips are really practical.”</p>
      <h4>— Harsha, CSE Student</h4>
    </div>
    <div class="t-card">
      <p>“The skill chart visualization keeps me motivated every week to keep learning and improving.”</p>
      <h4>— Meena, IT Student</h4>
    </div>
    <div class="t-card">
      <p>“Loved the job recommendations! It feels like LinkedIn but made just for students.”</p>
      <h4>— Sai Kiran, ECE Student</h4>
    </div>
  </div>
</section>

<footer>© 2025 SkillTracker — Building AI-Powered Futures for BTech Students</footer>

<script>
// Theme toggle with persistence
const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', ()=>{
  document.body.classList.toggle('light-mode');
  const isLight = document.body.classList.contains('light-mode');
  localStorage.setItem('theme', isLight ? 'light' : 'dark');
  themeToggle.textContent = isLight ? '🌞' : '🌙';
});
window.addEventListener('load', ()=>{
  const saved = localStorage.getItem('theme');
  if(saved === 'light'){
    document.body.classList.add('light-mode');
    themeToggle.textContent = '🌞';
  }
});
</script>

</body>
</html>

