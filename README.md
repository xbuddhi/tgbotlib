
# Cgram Telegram Bot Library

A custom Telegram bot library that handles commands, state-based conversations, and action commands via URL for managing webhooks. Designed for ease of use and comfort.

## Features

- Command-based conversation handling
- State management for user interactions
- URL-based action commands for managing Telegram webhooks
- Callback query handling for inline keyboard buttons
- Integration with Telegram's API for sending messages, handling webhooks, and more

## Getting Started

### Prerequisites

- PHP 7.x or later
- A valid Telegram Bot Token (You can get this by creating a bot with [BotFather](https://t.me/botfather) on Telegram)

### Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/your-username/telegram-bot-library.git
   cd telegram-bot-library
   ```

2. Install the necessary dependencies (e.g., cURL):

   ```bash
   sudo apt-get install php-curl
   ```

3. Rename `config.php.template` to `config.php` and set up your configuration in `config.php`:

   ```php
   define('TELEGRAM_BOT_TOKEN', 'your-telegram-bot-token');
   define('TELEGRAM_DEV_CHAT_ID', 'your-chat-id');
   define('TELEGRAM_WEBHOOK_URL', 'your-webhook-url');
   define('TELEGRAM_WEBHOOK_SECRET_TOKEN', 'your-webhook-secret-token');
   ```

### Running the Bot

1. To start the bot, point your server's webhook to your bot's URL. You can use the provided `ac` commands to manage the webhook.

## Webhook Management via URL

You can manage your Telegram bot's webhook by accessing specific URLs with the `ac` parameter:

### Sample URLs

- **Set Webhook**:

   ```url
   https://your-webhook-url.com/?ac=sw
   ```

- **Clear Webhook**:

   ```url
   https://your-webhook-url.com/?ac=cw
   ```

- **View Webhook Info**:

   ```url
   https://your-webhook-url.com/?ac=vw
   ```

### URL Parameters

- `sw`: Set the webhook with the predefined URL and secret token from `config.php`.
- `cw`: Clear the current webhook.
- `vw`: View the current webhook information.

## Example Usage

### Basic Command and State Handling

This bot handles simple conversations with users, such as asking for a nickname and age, and storing these values:

```php
$bot->addCommand('start', function (TelegramBot $bot, array $upd, int $chatId) {
    $fname = $upd["from"]["first_name"];
    $bot->sendMessage($chatId, "Hi *" . $fname . "* 👋\nWhat's your Nickname?");
    $bot->setState($chatId, 'awaiting_name');
});

$bot->addState('awaiting_name', function (TelegramBot $bot, array $upd, int $chatId, string $text) {
    $bot->setUserData($chatId, 'name', $text);
    $bot->sendMessage($chatId, "Nice to meet you, *" . $text . "*! How old are you?");
    $bot->setState($chatId, 'awaiting_age');
});

$bot->addState('awaiting_age', function (TelegramBot $bot, array $upd, int $chatId, string $text) {
    $bot->setUserData($chatId, 'age', $text);
    $name = $bot->getUserData($chatId, 'name');
    $bot->sendMessage($chatId, "Thanks, *" . $name . "*! You are " . $text . " years old.");
    $bot->resetState($chatId);
});
```

### Callback Query Handling

You can also handle inline keyboard buttons and stop the loading animation using `answerCallbackQuery`:

```php
$bot->addCommand('choose_color', function (TelegramBot $bot, array $upd, int $chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'Red', 'callback_data' => 'color_red']],
            [['text' => 'Blue', 'callback_data' => 'color_blue']],
        ]
    ];
    $bot->sendMessage($chatId, "Choose your favorite color:", $keyboard);
});

$bot->addCallbackQuery('color_red', function (TelegramBot $bot, array $callback, int $chatId) {
    $callbackQueryId = $callback['id'];
    $bot->answerCallbackQuery($callbackQueryId, "You chose Red!", false);
    $bot->sendMessage($chatId, "You chose *Red*!");
});

$bot->addCallbackQuery('color_blue', function (TelegramBot $bot, array $callback, int $chatId) {
    $callbackQueryId = $callback['id'];
    $bot->answerCallbackQuery($callbackQueryId, "You chose Blue!", false);
    $bot->sendMessage($chatId, "You chose *Blue*!");
});
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
