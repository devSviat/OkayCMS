<?php

namespace Okay\Helpers;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use Okay\Core\Response;
use Okay\Core\Settings;
use Throwable;

class OpenAiHelper
{
    private ClientContract $openAi;
    private Response $response;

    private string $model;
    private float $temperature;
    private int $frequencyPenalty;
    private int $presencePenalty;
    private int $maxTokens;
    private Settings $settings;

    public function __construct(
        Response $response,
        Settings $settings
    ) {
        $this->settings = $settings;
        $this->response = $response;
        $this->openAi = OpenAI::client((string)$settings->get('open_ai_api_key'));
        $this->model = ((string)$settings->get('open_ai_model')) ?:'gpt-3.5-turbo';
        $this->maxTokens = ((int)$settings->get('open_ai_max_tokens')) ?: 1000;
        $this->temperature = ((float)$settings->get('open_ai_temperature')) ?: 1.0;
        $this->frequencyPenalty = ((float)$settings->get('open_ai_frequency_penalty')) ?: 0;
        $this->presencePenalty = ((float)$settings->get('open_ai_presence_penalty')) ?: 0;
    }

    public function streamMetadata(string $userMessage, string $assistantMessage = '', bool $format = false)
    {
        $this->response->setContentType(RESPONSE_GPT_STREAM);
        $this->response->sendHeaders();
        if ($format) {
            $this->response->sendStream('data: <p>');
        }
        ignore_user_abort(true);

        try {
            $stream = $this->openAi->chat()->createStreamed([
                'model' => $this->model,
                'messages' => $this->buildMessages($userMessage, $assistantMessage),
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'frequency_penalty' => $this->frequencyPenalty,
                'presence_penalty' => $this->presencePenalty,
            ]);

            foreach ($stream as $response) {
                $content = $response->choices[0]->delta->content ?? '';

                if ($format && !empty(trim($content)) && strpos($content, "\n") !== false) {
                    $content = trim($content) . '</p><p>';
                }

                $this->response->sendStream('data: ' . $content);
                if (connection_aborted()) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $this->response->sendStream('data: ' . $e->getMessage());
        }

        if ($format) {
            $this->response->sendStream('data: </p>');
        }
        $this->response->sendStream("event: stop\ndata: stopped\n\n");
    }

    private function buildMessages(string $userMessage, string $assistantMessage = ''): array
    {
        $messages = [
            [
                "role" => "system",
                "content" => (string)$this->settings->get('ai_system_message'),
            ],
            [
                "role" => "user",
                "content" => $userMessage,
            ]
        ];

        if (!empty($assistantMessage)) {
            $messages[] = [
                "role" => "assistant",
                "content" => $assistantMessage
            ];
        }

        return $messages;
    }

    private function getModels(): ?array
    {
        try {
            $list = $this->openAi->models()->list();
        } catch (Throwable $e) {
            return null;
        }

        $models = [];
        foreach ($list->data as $model) {
            $models[] = ['id' => $model->id];
        }

        return $models;
    }

    public function getTextModels(): ?array
    {
        $models = $this->getModels();
        if ($models === null) {
            return null;
        }

        $textModels = array_filter($models, function ($model) {
            return strpos($model['id'], 'gpt-') !== false;
        });

        return $textModels;
    }
}
