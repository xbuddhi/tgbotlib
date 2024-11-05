<?php
ini_set('error_log', 'logs/errors.log');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Colombo');
// throw new Exception('Test Exception');
set_error_handler("telegramErrorHandler");
require_once "config.php";
function telegramErrorHandler($errno, $errstr, $errfile, $errline)
{
    try {
        $error = "Error: [$errno] $errstr - $errfile:$errline";
        error_log($error);
        sendMessage(
            TELEGRAM_DEV_CHAT_ID,
            "```" . $error . "```"
        );
        die();
    } catch (Exception $e) {
    }
}

// Send message to telegram chat
function sendMessage($chatId, $text, $replyToMessageId = null, $messageThreadID = null)
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = array(
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => "Markdown",
    );

    if ($replyToMessageId != null) {
        $data['reply_to_message_id'] = $replyToMessageId;
    }
    
    if ($messageThreadID != null) {
        $data['message_thread_id'] = $messageThreadID;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($result === false) {
        // Curl error
        echo "cURL Error: " . curl_error($ch);
    } else {
        // Check HTTP status code
        if ($httpcode >= 400) {
            // Handle HTTP error (e.g., 400 Bad Request)
            error_log("Telegram API error: " . $result);
        } else {
            // Successful request
            return $result;
        }
    }

    curl_close($ch);
}

class TelegramBot {
    private string $token;
    private bool $rawMode=true;
    private string $storagePath = 'assets/';
    private string $logsPath = 'logs/';
    private array $states = [];
    private array $userData = [];
    private array $commandHandlers = [];
    private array $stateHandlers = [];
    private array $callbackQueryHandlers = [];
    
    // Sets the webhook for the bot
    public function setWebhook(string $webhookUrl, string $secretToken) {
        $url = "https://api.telegram.org/bot{$this->token}/setWebhook?url={$webhookUrl}&secret_token={$secretToken}";
        $response = file_get_contents($url);
        return $response;
    }
    
     public function clearWebhook() {
        $url = "https://api.telegram.org/bot{$this->token}/setWebhook?url=";
        $response = file_get_contents($url);
        return $response;
    }
    
    public function getWebhookInfo() {
        $url = "https://api.telegram.org/bot{$this->token}/getWebhookInfo";
        $response = file_get_contents($url);
        return $response;
    }
    
    public function executeActions() {
        if (isset($_GET['ac'])) { 
            if ($_GET['ac'] === 'sw') {
                $webhookUrl = TELEGRAM_WEBHOOK_URL;
                $secretToken = TELEGRAM_WEBHOOK_SECRET_TOKEN;
                $response=$this->setWebhook($webhookUrl, $secretToken);
                $responseInfo=$this->getWebhookInfo($webhookUrl, $secretToken);
                
                // view response from setWebhook and getWebhookInfo
                $combinedResponse = array(
                    'setWebhookResponse' => json_decode($response, true),
                    'getWebhookInfoResponse' => json_decode($responseInfo, true)
                );
                
                $this->sendMessage(TELEGRAM_DEV_CHAT_ID,json_encode($combinedResponse, JSON_PRETTY_PRINT));
                echo json_encode($combinedResponse, JSON_PRETTY_PRINT);
                
            }
            if ($_GET['ac'] === 'cw') {
                $response=$this->clearWebhook();
                echo $response;
            }
            if ($_GET['ac'] === 'vw') {
                $response=$this->getWebhookInfo();
                echo $response;
            }
                http_response_code(200);
                header('Content-Type: application/json');
        }
    }
    
    public function __construct(bool $rawMode=false) {
        $this->rawMode=$rawMode;
        $this->token = TELEGRAM_BOT_TOKEN;
        $this->loadStates();
        $this->loadUserData();
        $this->executeActions();
    }

    // Adds a command and its handler
    public function addCommand(string $command, callable $handler): void {
        $this->commandHandlers[$command] = $handler;
    }

    // Adds a state handler
    public function addState(string $state, callable $handler): void {
        $this->stateHandlers[$state] = $handler;
    }

    // Adds a callback query handler
    public function addCallbackQuery(string $callbackQuery, callable $handler): void {
        $this->callbackQueryHandlers[$callbackQuery] = $handler;
    }

    // Handles incoming updates (commands or state-based messages)
    public function handleUpdate(): void {
        $update = json_decode(file_get_contents('php://input'), true);
        if ($this->rawMode && defined('TELEGRAM_DEV_CHAT_ID')) {
                $this->sendMessage(TELEGRAM_DEV_CHAT_ID, "```" . json_encode($update) . "```");
        }
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    // Handles a callback query
    private function handleCallbackQuery(array $callbackQuery): void {
        $callbackQueryID = $callbackQuery['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];

        if (isset($this->callbackQueryHandlers[$data])) {
            $handler = $this->callbackQueryHandlers[$data];
            $handler($this, $callbackQuery, $chatId, $callbackQueryID, $data);
        }
    }

    // Handles an incoming message
    private function handleMessage(array $message): void {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $message, $text);
        } else {
            $this->handleState($chatId, $message, $text);
        }
    }

    // Handles a command
    private function handleCommand(int $chatId, array $message, string $text): void {
        $commandParts = explode(' ', $text);
        $command = substr($commandParts[0], 1); // Remove the '/' character

        if (isset($this->commandHandlers[$command])) {
            $handler = $this->commandHandlers[$command];
            $handler($this, $message, $chatId, $text);
        }
    }

    // Handles a message based on the user's current state
    private function handleState(int $chatId, array $message, string $text): void {
        $currentState = $this->getState($chatId);

        if (isset($this->stateHandlers[$currentState])) {
            $handler = $this->stateHandlers[$currentState];
            $handler($this, $message, $chatId, $text);
        }
    }

    // Get the current state of the user
    public function getState(int $chatId): string {
        return $this->states[$chatId] ?? 'start';
    }

    // Set the user's state
    public function setState(int $chatId, string $state): void {
        $this->states[$chatId] = $state;
        $this->saveStates();
    }

    // Resets the user's state
    public function resetState(int $chatId): void {
        unset($this->states[$chatId]);
        $this->saveStates();
    }

    // Load conversation states from storage
    private function loadStates(): void {
        if (file_exists($this->storagePath . 'conversation_states.json')) {
            $this->states = json_decode(file_get_contents($this->storagePath . 'conversation_states.json'), true) ?? [];
        }
    }

    // Save conversation states to storage
    private function saveStates(): void {
        file_put_contents($this->storagePath . 'conversation_states.json', json_encode($this->states, JSON_PRETTY_PRINT));
    }

    // Get user-specific data
    public function getUserData(int $chatId, string $key): mixed {
        return $this->userData[$chatId][$key] ?? null;
    }

    // Set user-specific data
    public function setUserData(int $chatId, string $key, mixed $value): void {
        $this->userData[$chatId][$key] = $value;
        $this->saveUserData();
    }

    // Load user data from storage
    private function loadUserData(): void {
        if (file_exists($this->storagePath . 'user_data.json')) {
            $this->userData = json_decode(file_get_contents($this->storagePath . 'user_data.json'), true) ?? [];
        }
    }

    // Save user data to storage
    private function saveUserData(): void {
        file_put_contents($this->storagePath . 'user_data.json', json_encode($this->userData, JSON_PRETTY_PRINT));
    }

    // Sends a message to the user
    public function sendMessage(int $chatId, string $text, array $replyMarkup = null, int $messageThreadId = null): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        return $this->makeApiRequest('sendMessage', $params);
    }

    // Sends a file to the user using either file ID or local file path
    public function sendFile(int $chatId, string $file): array {
        $params = [
            'chat_id' => $chatId,
        ];
    
        // Check if the file is a local file or file_id
        if (file_exists($file)) {
            // If it's a local file, use CURLFile to send it
            $params['document'] = new CURLFile(realpath($file));
        } else {
            // If it's a file_id, send it directly
            $params['document'] = $file;
        }
    
        return $this->makeApiRequest('sendDocument', $params);
    }

    
    // Sends a file to the user using file ID
    public function sendPhoto(int $chatId, string $fileId,$caption=null): array {
        $params = [
            'chat_id' => $chatId,
            'photo' => $fileId,
            'caption' => $caption,
            'parse_mode' => 'Markdown',
        ];

        return $this->makeApiRequest('sendPhoto', $params);
    }

    // Forwards a message to another chat
    public function forwardMessage(int $chatId, int $fromChatId, int $messageId, int $messageThreadId = null): array {
        $params = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ];

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        return $this->makeApiRequest('forwardMessage', $params);
    }

    // Respond to a callback query
    public function answerCallbackQuery(string $callbackQueryId, string $text = null, bool $showAlert = false): array {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];

        return $this->makeApiRequest('answerCallbackQuery', $params);
    }

    // Edits the text of an existing message
    public function editMessageText(int $chatId, int $messageId, string $text, array $replyMarkup = null): array {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->makeApiRequest('editMessageText', $params);
    }
    
    // Fetch the user's profile photos
    public function getUserProfilePhotos(int $userId, int $limit = 1, int $offset = 0): array {
        $params = [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ];
    
        return $this->makeApiRequest('getUserProfilePhotos', $params);
    }
    
    
    // Example method to send the user's profile photo to them
    public function sendUserProfilePhoto(int $chatId, int $userId): void {
        
        
        $photos = $this->getUserProfilePhotos($userId);
        if (!empty($photos['result']['photos'])) {
            $fileId = $photos['result']['photos'][0][0]['file_id']; // First photo, first size
            $this->sendPhoto($chatId, $fileId);
        } else {
            $this->sendMessage($chatId, "Your profile photo is either not set or hidden due to your privacy settings, which prevents the bot from accessing it.\n\nPlease set a profile photo or adjust your privacy settings to make it visible to everyone.");
        }
    }

    // Logs messages to a file
    public function log(string $message, string $level = 'INFO'): void {
        $logFile = $this->logsPath . 'bot.log';  // Define the log file path
        $date = date('Y-m-d H:i:s');
        $logMessage = "[{$date}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    // Deletes a specific message from a chat
    public function deleteMessage(int $chatId, int $messageId): array {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
    
        return $this->makeApiRequest('deleteMessage', $params);
    }

    // Downloads a file from Telegram and returns the local file path
    public function downloadFile(string $fileId,string $localFilePath): ?string {
        // Step 1: Get file path from Telegram
        $fileInfo = $this->makeApiRequest('getFile', ['file_id' => $fileId]);
        
        if (isset($fileInfo['ok']) && $fileInfo['ok'] && isset($fileInfo['result']['file_path'])) {
            $filePath = $fileInfo['result']['file_path'];
            $fileUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
    
            // Step 2: Download the file
            // $localFilePath = $this->storagePath . basename($filePath);
            $fileContent = file_get_contents($fileUrl);
            
            if ($fileContent !== false) {
                // Save the file locally
                file_put_contents($localFilePath, $fileContent);
                $this->log("File downloaded: {$localFilePath}");
                return $localFilePath; // Return the local path
            } else {
                $this->log("Failed to download file: {$fileUrl}", 'ERROR');
            }
        } else {
            $this->log("Failed to get file information for file ID: {$fileId}", 'ERROR');
        }
    
        return null; // Return null if file download failed
    }

    // Generic method for making API requests to the Telegram Bot API
    private function makeApiRequest(string $method, array $params): array {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);
        //$this->sendMessage(TELEGRAM_DEV_CHAT_ID,"```".json_encode($result)."```" );
        $this->log($result);

        return json_decode($result, true) ?? [];
    }
    

}
