<?php
header("Content-Type: application/json");
$data = json_decode(file_get_contents("php://input"), true);
$goal = trim($data["goal"] ?? "");

if($goal==""){ echo json_encode(["plan"=>"Please provide a career goal."]); exit; }

$paths = [
  "data" => [
    "Step 1 – Learn Python & SQL basics.",
    "Step 2 – Master statistics and machine learning.",
    "Step 3 – Build data-driven projects.",
    "Step 4 – Apply for internships."
  ],
  "ai" => [
    "Step 1 – Understand neural networks fundamentals.",
    "Step 2 – Practice TensorFlow / PyTorch.",
    "Step 3 – Train models on Kaggle datasets.",
    "Step 4 – Apply for ML Engineer roles."
  ],
  "web" => [
    "Step 1 – HTML + CSS + JS mastery.",
    "Step 2 – React / Node stack.",
    "Step 3 – Host projects on GitHub.",
    "Step 4 – Join startups as full-stack dev."
  ]
];
$key = "data";
if(stripos($goal,"ai")!==false||stripos($goal,"ml")!==false) $key="ai";
elseif(stripos($goal,"web")!==false) $key="web";

echo json_encode(["plan"=> "<ul><li>".implode("</li><li>",$paths[$key])."</li></ul>"]);
?>

