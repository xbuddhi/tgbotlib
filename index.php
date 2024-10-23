<?php
// This is a simple example of a Telegram Bot using the TelegramBot class.

require_once "pkg/TelegramBot.php";

$bot = new TelegramBot();


#### ##########################################################
#### Conversation Handling: Starting the conversation and states
#### ##########################################################

/**
 * Command to start a conversation
 * This command will prompt the user for their nickname.
 */
$bot->addCommand('start', function (TelegramBot $bot, array $upd, int $chatId) {
    $fname = $upd["from"]["first_name"];
    $bot->sendMessage($chatId, "Hi *" . $fname . "* 👋\nWhat's your Nickname?");
    // Set user's state to 'awaiting_name'
    $bot->setState($chatId, 'awaiting_name');
});

/**
 * State handler: Awaiting the user's nickname
 * After getting the nickname, it will ask for the user's age.
 */
$bot->addState('awaiting_name', function (TelegramBot $bot, array $upd, int $chatId, string $text) {
    // Store the user's nickname
    $bot->setUserData($chatId, 'name', $text);

    // Ask for age
    $bot->sendMessage($chatId, "Nice to meet you, *" . $text . "*! How old are you?");
    
    // Update state to 'awaiting_age'
    $bot->setState($chatId, 'awaiting_age');
});

/**
 * State handler: Awaiting the user's age
 * After getting the age, the bot concludes the conversation.
 */
$bot->addState('awaiting_age', function (TelegramBot $bot, array $upd, int $chatId, string $text) {
    // Store the user's age
    $bot->setUserData($chatId, 'age', $text);

    // Retrieve stored name
    $name = $bot->getUserData($chatId, 'name');

    // Reply with a message that concludes the interaction
    $bot->sendMessage($chatId, "Thanks, *" . $name . "*! You are " . $text . " years old. Have a great day!");

    // Reset state to start
    $bot->resetState($chatId);
});

/**
 * Command to stop the conversation
 * This resets the user's state and stops the current interaction.
 */
$bot->addCommand('stop', function (TelegramBot $bot, array $upd, int $chatId) {
    $bot->resetState($chatId);
    $bot->sendMessage($chatId, "Conversation has been stopped. If you want to start again, type /start.");
});

#### ########################################################
#### End of Conversation Handling (commands and states)
#### ########################################################


#### ########################################################
#### Callback Query Handling: Handling button interactions
#### ########################################################

/**
 * Command to initiate a conversation with inline buttons
 * This command presents options to either choose "Red" or "Blue".
 */
$bot->addCommand('choose_color', function (TelegramBot $bot, array $upd, int $chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'Red', 'callback_data' => 'color_red']],
            [['text' => 'Blue', 'callback_data' => 'color_blue']],
        ]
    ];
    $bot->sendMessage($chatId, "Choose your favorite color:", $keyboard);
    $bot->setState($chatId, 'awaiting_color_choice');
});

/**
 * Callback Query: Handling "Red" button
 * This handler will acknowledge the "Red" button press and stop the animation.
 */
$bot->addCallbackQuery('color_red', function (TelegramBot $bot, array $callback, int $chatId) {
    // Acknowledge the callback and stop the loading animation
    $callbackQueryId = $callback['id']; // Retrieve the callback query ID
    $bot->answerCallbackQuery($callbackQueryId, "You chose Red!", false);
    
    // Send a confirmation message
    $bot->sendMessage($chatId, "You chose *Red*!");
    
    // Reset the conversation state
    $bot->resetState($chatId);
});

/**
 * Callback Query: Handling "Blue" button
 * This handler will acknowledge the "Blue" button press and stop the animation.
 */
$bot->addCallbackQuery('color_blue', function (TelegramBot $bot, array $callback, int $chatId) {
    // Acknowledge the callback and stop the loading animation
    $callbackQueryId = $callback['id']; // Retrieve the callback query ID
    $bot->answerCallbackQuery($callbackQueryId, "You chose Blue!", false);
    
    // Send a confirmation message
    $bot->sendMessage($chatId, "You chose *Blue*!");
    
    // Reset the conversation state
    $bot->resetState($chatId);
});

#### ##############################################################
#### End of Callback Query Handling (button interaction handlers)
#### ##############################################################

// Handling the update (this will be triggered for each incoming message/command)
$bot->handleUpdate();



#### ##########################################################
#### Action Command (ac) Handling: Webhook actions via URL
#### ##########################################################

/**
 * Handling URL-based `ac` commands for managing webhooks.
 * Triggered when accessing the URL with parameters like:
 * - `/?ac=sw`  -> Set the webhook
 * - `/?ac=cw`  -> Clear the webhook
 * - `/?ac=vw`  -> View webhook info
 * 
 *  eg: https://your-bot-url.com/?ac=sw
 */
