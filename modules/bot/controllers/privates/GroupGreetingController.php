<?php

namespace app\modules\bot\controllers\privates;

use Yii;
use app\modules\bot\components\Controller;
use app\modules\bot\models\Chat;
use app\modules\bot\models\ChatSetting;
use app\modules\bot\components\helpers\Emoji;

/**
 * Class GroupGreetingController
 *
 * @package app\modules\bot\controllers\privates
 */
class GroupGreetingController extends Controller
{
    /**
     * @return array
     */
    public function actionIndex($chatId = null)
    {
        $chat = Chat::findOne($chatId);
        $telegramUser = $this->getTelegramUser();

        if (!isset($chat)) {
            return [];
        }

        $chatTitle = $chat->title;

        $statusSetting = $chat->getSetting(ChatSetting::GREETING_STATUS);
        $statusOn = ($statusSetting->value == ChatSetting::GREETING_STATUS_ON);

        $messageSetting = $chat->getSetting(ChatSetting::GREETING_MESSAGE);

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('index', compact('chatTitle', 'telegramUser', 'messageSetting')),
                [
                        [
                            [
                                'callback_data' => self::createRoute('set-status', [
                                    'chatId' => $chatId,
                                ]),
                                'text' => $statusOn ? Emoji::STATUS_ON . ' ON' : Emoji::STATUS_OFF . ' OFF',
                            ],
                        ],
                        [
                            [
                                'callback_data' => self::createRoute('set-message', [
                                    'chatId' => $chatId,
                                ]),
                                'text' => Yii::t('bot', 'Message'),
                            ],
                        ],
                        [
                            [
                                'callback_data' => GroupController::createRoute('view', [
                                    'chatId' => $chatId,
                                ]),
                                'text' => Emoji::BACK,
                            ],
                            [
                                'callback_data' => MenuController::createRoute(),
                                'text' => Emoji::MENU,
                            ],
                        ]
                    ],
                    [
                        'disablePreview' => true,
                    ]
            )
            ->build();
    }

    public function actionSetStatus($chatId = null)
    {
        $chat = Chat::findOne($chatId);

        if (!isset($chat)) {
            return [];
        }

        $statusSetting = $chat->getSetting(ChatSetting::GREETING_STATUS);

        if ($statusSetting->value == ChatSetting::GREETING_STATUS_ON) {
            $statusSetting->value = ChatSetting::GREETING_STATUS_OFF;
        } else {
            $statusSetting->value = ChatSetting::GREETING_STATUS_ON;
        }

        $statusSetting->save();

        return $this->actionIndex($chatId);
    }

    public function actionSetMessage($chatId = null)
    {
        $chat = Chat::findOne($chatId);

        if (!isset($chat)) {
            return [];
        }

        $this->getState()->setName(self::createRoute('save-message', [
                'chatId' => $chatId,
            ]));

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('set-message'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('index', [
                                'chatId' => $chatId,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                    ]
                ],
                [
                    'disablePreview' => true,
                ]
            )
            ->build();
    }

    public function actionSaveMessage($chatId = null)
    {
        $chat = Chat::findOne($chatId);

        if (!isset($chat)) {
            return [];
        }

        $text = $this->getUpdate()->getMessage()->getText();
        $text = strip_tags($text);
        // TODO Convert markdown to html tags
        $textLenght = strlen($text);

        if (!(($textLenght >= ChatSetting::GREETING_MESSAGE_LENGHT_MIN) && ($textLenght <= ChatSetting::GREETING_MESSAGE_LENGHT_MAX))) {
            return $this->getResponseBuilder()
                ->deleteMessage()
                ->build();
        }

        $messageSetting = $chat->getSetting(ChatSetting::GREETING_MESSAGE);
        $messageSetting->value = $text;
        $messageSetting->save();

        $this->getState()->setName(null);

        return $this->runAction('index', [
            'chatId' => $chatId,
        ]);
    }
}
