<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;

class AiController
{
    public function index(): void
    {
        global $twig;
        echo $twig->render('ai.twig');
    }

    public function assist(): void
    {
        header('Content-Type: application/json');
        $prompt = $_POST['prompt'] ?? '';
        if (empty(Config::$geminiApiKey)) {
            echo json_encode(['reply' => 'A chave do Google Gemini não está configurada no Config.php.']);
            exit;
        }
        if (!function_exists('curl_init')) {
            echo json_encode(['reply' => 'Erro: A extensão cURL do PHP não está ativada.']);
            exit;
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . Config::$geminiApiKey;
        $payload = ['contents' => [['parts' => [['text' => "Você é um assistente especialista em banco de dados MySQL. O usuário vai descrever um sistema e você deve responder APENAS com o código SQL CREATE TABLE necessário. Não adicione explicações em texto, apenas o bloco SQL.\n\nPedido do usuário: " . $prompt]]]]];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            echo json_encode(['reply' => "Erro no cURL: " . $curlError]);
            exit;
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $apiError = $data['error']['message'] ?? 'Erro desconhecido';
            echo json_encode(['reply' => "Erro do Gemini (HTTP $httpCode): " . $apiError]);
            exit;
        }
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'A IA não retornou texto.';
        echo json_encode(['reply' => nl2br(htmlspecialchars($reply))]);
    }
}