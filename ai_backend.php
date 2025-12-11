<?php
header("Content-Type: application/json");
$data = json_decode(file_get_contents("php://input"), true);
$message = strtolower(trim($data['message']));

$response = "I'm not sure about that yet. Could you rephrase your question?";

if (strpos($message, 'python') !== false) {
  $response = "Python is one of the most demanded skills. Start with basics, then move to Pandas, Flask, and Django.";
} elseif (strpos($message, 'career') !== false) {
  $response = "Think about your passions. Focus on in-demand tech domains like Data Science, AI, Cybersecurity, or Full Stack.";
} elseif (strpos($message, 'project') !== false) {
  $response = "Build real-world projects — portfolio websites, AI apps, or automation tools. They showcase your skills best!";
} elseif (strpos($message, 'resume') !== false) {
  $response = "Keep your resume one-page. Focus on projects, skills, and measurable achievements.";
} elseif (strpos($message, 'java') !== false) {
  $response = "Java is powerful for backend systems and Android. Learn OOP, Spring Boot, and APIs.";
} elseif (strpos($message, 'data') !== false) {
  $response = "Learn SQL, Excel, and visualization (Power BI / Tableau). Then dive into Python + Pandas for Data Science.";
} elseif (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
  $response = "Hey there! 👋 I’m your SkillTracker AI. What do you want to learn today?";
}

echo json_encode(["response" => $response]);
?>

