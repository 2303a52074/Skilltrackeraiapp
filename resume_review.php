<?php
// resume_review.php
// Upload + analyze resume (pdf, docx, txt)
// Place in project root and open via browser.

session_start();
include 'includes/config.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$userQ = $conn->prepare("SELECT name, email FROM users WHERE id=?");
$userQ->bind_param("i", $user_id);
$userQ->execute();
$user = $userQ->get_result()->fetch_assoc();

// -------------------- Utilities --------------------
function sanitize_text($t){
  $t = preg_replace('/\s+/', ' ', $t);
  $t = trim(strip_tags($t));
  return $t;
}

function extract_text_from_docx($file) {
  $zip = new ZipArchive();
  if ($zip->open($file) === true) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml) {
      $text = preg_replace('/<[^>]+>/', ' ', $xml);
      return sanitize_text($text);
    }
  }
  return '';
}

function extract_text_from_pdf($file) {
  // Try using pdftotext (poppler). If not available, return empty and the caller will fallback.
  $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('res_') . '.txt';
  // escape file path
  $fileEsc = escapeshellarg($file);
  $outEsc = escapeshellarg($out);
  $cmd = "pdftotext -layout $fileEsc $outEsc 2>&1";
  @exec($cmd, $output, $ret);
  if ($ret === 0 && file_exists($out)) {
    $txt = file_get_contents($out);
    @unlink($out);
    return sanitize_text($txt);
  } else {
    return ''; // caller will handle
  }
}

function extract_text_from_txt($file) {
  $txt = file_get_contents($file);
  return sanitize_text($txt);
}

// basic list of skills to scan for (extend this as you want)
function get_skill_keywords(){
  return [
    'python','java','c++','c','javascript','html','css','react','node','django','flask',
    'sql','mysql','postgresql','mongodb','tensorflow','pytorch','keras','scikit-learn',
    'machine learning','deep learning','data science','nlp','computer vision','git','docker',
    'kubernetes','aws','azure','gcp','linux','bash','rest api','spring boot','php','laravel',
    'problem solving','algorithms','data structures','system design'
  ];
}

// action verbs for resume strength
function get_action_verbs(){
  return ['developed','designed','implemented','improved','optimized','led','created','deployed','built','engineered','automated','reduced','increased','managed','delivered','trained','evaluated'];
}

// detect email
function find_email($text){
  if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $m)) return $m[0];
  return '';
}
// detect phone (simple)
function find_phone($text){
  if (preg_match('/(\+?\d{2,4}[\s-]?)?(\d{3}[\s-]?\d{3}[\s-]?\d{4}|\d{10})/', $text, $m)) return $m[0];
  return '';
}

// scoring function
function analyze_resume($text) {
  $res = [];
  $res['raw_length'] = strlen($text);
  $words = str_word_count(strtolower($text), 1);
  $res['word_count'] = count($words);
  $skills = get_skill_keywords();
  $skill_found = [];
  foreach ($skills as $sk) {
    // fuzzy: if multiword skill, do strpos
    if (strpos(strtolower($text), strtolower($sk)) !== false) $skill_found[] = $sk;
  }
  $res['skills_found'] = array_values(array_unique($skill_found));
  // action verbs count
  $verbs = get_action_verbs();
  $verb_count = 0;
  foreach ($verbs as $v) {
    $verb_count += substr_count(strtolower($text), $v);
  }
  $res['action_verbs'] = $verb_count;

  // projects / experience cues
  $proj_cues = ['project','internship','experience','worked on','built','contributed','research'];
  $proj_count = 0;
  foreach ($proj_cues as $c) $proj_count += substr_count(strtolower($text), $c);
  $res['project_cues'] = $proj_count;

  // contact detection
  $res['email'] = find_email($text);
  $res['phone'] = find_phone($text);

  // Basic scoring heuristics
  $score = 0;
  // contact info: 15
  $score += ($res['email'] ? 8 : 0) + ($res['phone'] ? 7 : 0);
  // skills: up to 40 (min(40, 10 * number_of_skills_found))
  $score += min(40, 10 * count($res['skills_found']));
  // action verbs & projects: up to 25
  $score += min(15, $res['action_verbs'] * 3);
  $score += min(10, $res['project_cues'] * 2);
  // length: good if 250-800 words -> add up to 10
  $wc = $res['word_count'];
  if ($wc >= 250 && $wc <= 800) $score += 10;
  elseif ($wc >= 150 && $wc < 250) $score += 6;
  elseif ($wc > 800) $score += 6;

  $res['score'] = min(100, intval($score));

  // suggestions
  $suggest = [];
  if (!$res['email']) $suggest[] = "Add a professional email address (e.g., name@domain.com).";
  if (!$res['phone']) $suggest[] = "Add a phone number with country code for recruiters to contact you.";
  if (count($res['skills_found']) < 3) $suggest[] = "List more technical skills and tools you used (languages, frameworks, cloud, DB).";
  if ($res['action_verbs'] < 3) $suggest[] = "Use stronger action verbs (developed, implemented, optimized) in experience/project bullets.";
  if ($res['project_cues'] < 2) $suggest[] = "Add at least 2 small projects with your role, tech stack, and measurable impact.";
  if ($res['word_count'] < 150) $suggest[] = "Your resume is very short — expand projects/achievements. Aim for 1 page (250-500 words).";
  if ($res['word_count'] > 1200) $suggest[] = "Resume is long — trim irrelevant details. Keep 1–2 pages max.";
  if (count($res['skills_found']) >= 5) $suggest[] = "Great: multiple relevant skills present. Consider ranking them by proficiency.";

  // Recommend courses based on missing skills (simple)
  $recommend = [];
  if (!in_array('git', $res['skills_found'])) $recommend[] = ['Git & GitHub', 'https://www.freecodecamp.org/learn/git/'];
  if (!in_array('sql', $res['skills_found'])) $recommend[] = ['SQL for Data', 'https://www.coursera.org/courses?query=sql'];
  if (!in_array('python', $res['skills_found'])) $recommend[] = ['Python Basics', 'https://www.coursera.org/specializations/python'];
  if (!in_array('machine learning', $res['skills_found']) && in_array('python', $res['skills_found'])) $recommend[] = ['Intro to ML (Coursera)', 'https://www.coursera.org/learn/machine-learning'];

  $res['suggestions'] = $suggest;
  $res['recommendations'] = $recommend;

  return $res;
}

// -------------------- Handle Upload --------------------
$analysis = null;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume_file'])) {
  $uploadDir = __DIR__ . '/uploads/resumes/';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

  $f = $_FILES['resume_file'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $allowed = ['pdf','docx','txt'];
  if (!in_array($ext, $allowed)) $err = "Only PDF, DOCX or TXT files allowed.";
  else {
    $saved = $uploadDir . uniqid('resume_') . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $saved)) $err = "Upload failed.";
    else {
      // extract text
      $text = '';
      if ($ext === 'docx') {
        $text = extract_text_from_docx($saved);
      } elseif ($ext === 'txt') {
        $text = extract_text_from_txt($saved);
      } elseif ($ext === 'pdf') {
        $text = extract_text_from_pdf($saved);
        if (!$text) {
          // fallback: try PHAR library? we will inform user about pdftotext missing
          $err = "PDF text extraction failed. Please install 'pdftotext' (Poppler) or upload a .docx/.txt resume.";
        }
      }
      if (!$err) {
        if (strlen($text) < 10) $err = "Could not extract readable text from the file. Try DOCX or TXT, or install pdftotext for PDFs.";
        else {
          $analysis = analyze_resume($text);
          $analysis['preview'] = substr($text,0,2000);
          // optionally store review in DB (skipped here)
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AI Resume Reviewer — SkillTracker</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
body{font-family:'Poppins',sans-serif;background:#071025;color:#e6f7f1;padding:30px;}
.container{max-width:1000px;margin:0 auto;}
.card{background:rgba(255,255,255,0.03);padding:20px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);margin-bottom:18px;}
h1{color:#00ffe0}
input[type=file]{background:rgba(255,255,255,0.02);padding:8px;border-radius:8px;color:#fff;}
button{background:linear-gradient(90deg,#00ffe0,#00b3ff);border:none;padding:10px 16px;border-radius:10px;color:#000;font-weight:600;cursor:pointer;}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(0,255,224,0.12);color:#00ffe0;margin-right:8px;font-weight:600;}
.score{font-size:48px;color:#00ffe0;font-weight:700}
.list{margin-left:18px}
.small{color:rgba(255,255,255,0.6);font-size:0.95rem}
pre{white-space:pre-wrap;background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);color:#fff;overflow:auto}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>AI Resume Reviewer</h1>
    <p class="small">Upload your resume (PDF, DOCX, TXT). The reviewer will extract text and give a score + suggestions.</p>
    <p class="small">Note: For PDFs, installing <strong>pdftotext</strong> (Poppler) gives the best extraction. If not installed, upload a DOCX or TXT.</p>

    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="resume_file" required>
      <button type="submit">Analyze Resume</button>
    </form>
  </div>

  <?php if ($err): ?>
    <div class="card"><strong style="color:#ff7a7a">Error:</strong> <?php echo htmlspecialchars($err); ?></div>
  <?php endif; ?>

  <?php if ($analysis): ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div class="badge">User: <?php echo htmlspecialchars($user['name']); ?></div>
          <div class="small">Email: <?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        <div style="text-align:right">
          <div class="small">Resume Score</div>
          <div class="score"><?php echo $analysis['score']; ?></div>
          <div class="small">Word count: <?php echo $analysis['word_count']; ?></div>
        </div>
      </div>
      <hr style="margin:12px 0;border-color:rgba(255,255,255,0.04)">
      <h3 style="color:#fff">Strengths</h3>
      <ul class="list">
        <?php if (!empty($analysis['skills_found'])): ?>
          <li><strong>Skills detected:</strong> <?php echo htmlspecialchars(implode(', ', $analysis['skills_found'])); ?></li>
        <?php else: ?>
          <li><strong>No technical skills detected.</strong></li>
        <?php endif; ?>
        <li><strong>Action verbs used:</strong> <?php echo $analysis['action_verbs']; ?></li>
        <li><strong>Project / Experience mentions:</strong> <?php echo $analysis['project_cues']; ?></li>
      </ul>

      <h3 style="color:#fff">Areas to Improve</h3>
      <ul class="list">
        <?php if (count($analysis['suggestions'])>0): foreach ($analysis['suggestions'] as $s): ?>
          <li><?php echo htmlspecialchars($s); ?></li>
        <?php endforeach; else: ?>
          <li>None — your resume looks good. Consider fine-tuning phrasing and metrics.</li>
        <?php endif; ?>
      </ul>

      <h3 style="color:#fff">Recommended Learning</h3>
      <ul class="list">
        <?php if (count($analysis['recommendations'])>0): foreach ($analysis['recommendations'] as $r): ?>
          <li><a href="<?php echo $r[1]; ?>" target="_blank" style="color:#00ffe0"><?php echo htmlspecialchars($r[0]); ?></a></li>
        <?php endforeach; else: ?>
          <li>No specific course recommendations — good job!</li>
        <?php endif; ?>
      </ul>

      <h3 style="color:#fff">Resume Preview (first 2000 characters)</h3>
      <pre><?php echo htmlspecialchars($analysis['preview']); ?></pre>
    </div>
  <?php endif; ?>

  <div class="card small">
    <strong>Tips to improve resume quickly</strong>
    <ul>
      <li>Use measurable results (e.g., "Reduced inference time by 30%").</li>
      <li>List 3-6 core skills at the top and tools used per project.</li>
      <li>Use action verbs at the start of bullets (Developed, Implemented, Optimized).</li>
      <li>Keep resume to 1 page for freshers; 1–2 pages for experienced candidates.</li>
    </ul>
  </div>

  <div class="card small">
    <strong>Poppler / pdftotext installation (optional, for PDFs)</strong>
    <ul>
      <li><strong>Windows (recommended)</strong>: Install Poppler and add to PATH. Example: download from <em>Poppler for Windows</em> and add bin folder to PATH.</li>
      <li><strong>Linux (Debian/Ubuntu)</strong>: <code>sudo apt-get install poppler-utils</code></li>
      <li>After installation, re-upload a PDF and resume text extraction will work better.</li>
    </ul>
  </div>
</div>
</body>
</html>
