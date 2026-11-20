<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

use Angeo\AiAssist\Api\ProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * One question, start to finish: facts → cache → limit → provider → store.
 */
class Assistant
{
    /**
     * @param array<string,ProviderInterface> $providers keyed by Config::PROVIDER_*
     */
    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $collector,
        private readonly PromptBuilder $prompts,
        private readonly AnswerStore $store,
        private readonly array $providers = []
    ) {
    }

    /**
     * @return array{answer:string,cached:bool,provider:string,model:string,remaining:int}
     * @throws LocalizedException
     */
    public function ask(string $source, string $ref, string $task, string $question, int $adminUserId, string $language): array
    {
        if (!$this->config->isReady()) {
            throw new LocalizedException(new Phrase(
                'The AI assistant is off. Turn it on and add an API key in Stores → Configuration → Angeo AEO → AI Assistant.'
            ));
        }
        if (!in_array($task, PromptBuilder::TASKS, true)) {
            throw new LocalizedException(new Phrase('Unknown request.'));
        }

        $context  = $this->collector->collect($source, $ref);
        $question = $this->prompts->cleanQuestion($question);
        if ($task === PromptBuilder::TASK_ASK && $question === '') {
            throw new LocalizedException(new Phrase('Type a question first.'));
        }

        $provider = $this->config->getProvider();
        $model    = $this->config->getModel();
        // The language is part of the question: an answer in German is not
        // the cached answer for an admin who reads English.
        $qHash    = hash('sha256', $task . "\n" . mb_strtolower($question) . "\n" . $language);
        $cHash    = $context->hash();
        $limit    = $this->config->getDailyLimit();
        $used     = $this->store->countToday($adminUserId);

        $cached = $this->store->find($source, $ref, $task, $qHash, $cHash, $model, $this->config->getCacheDays());
        if ($cached !== null) {
            return ['answer' => $cached, 'cached' => true, 'provider' => $provider, 'model' => $model, 'remaining' => max(0, $limit - $used)];
        }

        if ($used >= $limit) {
            throw new LocalizedException(new Phrase(
                'You have used today\'s %1 AI answers. The limit resets at midnight UTC and can be changed in the AI Assistant settings.',
                [$limit]
            ));
        }

        $client = $this->providers[$provider] ?? null;
        if (!$client instanceof ProviderInterface) {
            throw new LocalizedException(new Phrase('The selected AI provider is not available.'));
        }

        $prompt = $this->prompts->build($context, $task, $question, $language);
        $result = $client->complete(
            $prompt['system'],
            $prompt['user'],
            $this->config->getApiKey(),
            $model,
            $this->config->getMaxOutputTokens(),
            $this->config->getTimeout()
        );

        $answer = trim($result['text']);
        if (($result['stop'] ?? null) !== null && in_array(strtolower((string) $result['stop']), ['max_tokens', 'length', 'max_output_tokens'], true)) {
            $answer .= "\n\n_" . __('The answer was cut at the length limit.') . '_';
        }

        $this->store->save(
            $adminUserId,
            $source,
            $ref,
            $task,
            $qHash,
            $cHash,
            $provider,
            $model,
            $answer,
            $result['input_tokens'] ?? null,
            $result['output_tokens'] ?? null
        );

        return ['answer' => $answer, 'cached' => false, 'provider' => $provider, 'model' => $model, 'remaining' => max(0, $limit - $used - 1)];
    }
}
