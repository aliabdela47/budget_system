<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoloader
require_once __DIR__ . '/../config.php';          // Your API Key
require_once __DIR__ . '/includes/init.php';    // Your app init

use Google\AI\GenerativelModel;
use Google\AI\GenerativeAI;
use Parsedown;

header('Content-Type: application/json');

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}
if (!csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF validation failed.']);
    exit;
}
if (empty(GEMINI_API_KEY)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'AI API key is not configured.']);
    exit;
}

// Get the summarized data sent from the browser
$data = json_decode($_POST['data'] ?? '', true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No data provided for analysis.']);
    exit;
}

// Prompt Engineering: Instruct Gemini on its role and task
$prompt = "
You are a senior financial analyst for a government health bureau in Ethiopia. Your task is to analyze the following budget data summary and provide actionable insights. The currency is Ethiopian Birr (ETB).

Here is the data for the period you are analyzing:
- Filter Context: {$data['filter_context']}
- Total Allocated Budget: {$data['total_allocated']} ETB
- Total Spent: {$data['total_used']} ETB
- Remaining Budget: {$data['total_remaining']} ETB
- Overall Budget Utilization: {$data['utilization_percentage']}%

Top 5 Spending Owners (Directorates):
{$data['top_spenders_text']}

Spending Trend (Month over Month):
{$data['trend_text']}

Key Alerts:
{$data['alerts_text']}

Based on this data, provide a concise analysis in Markdown format. Structure your response as follows:
1.  **Executive Summary:** A brief, high-level overview of the financial situation.
2.  **Key Observations:** Use a bulleted list to highlight the most important findings (e.g., high burn rate, underspending, specific directorates driving costs).
3.  **Potential Risks:** Identify any potential financial risks or anomalies (e.g., risk of overspending, inefficient use of funds).
4.  **Actionable Recommendations:** Suggest 2-3 clear, actionable steps the bureau management should consider.

Keep the tone professional, objective, and data-driven.
";

try {
    $client = new GenerativeAI(GEMINI_API_KEY);
    $model = $client->geminiPro();
    $response = $model->generateContent($prompt);

    $markdownText = $response->text();

    // Convert Markdown to HTML for safe rendering
    $Parsedown = new Parsedown();
    $htmlContent = $Parsedown->text($markdownText);

    echo json_encode(['success' => true, 'html' => $htmlContent]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'The AI model could not process the request. Details: ' . $e->getMessage()]);
}