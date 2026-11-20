<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Config\Source;

use Angeo\AiAssist\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Provider implements OptionSourceInterface
{
    /**
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::PROVIDER_ANTHROPIC, 'label' => __('Anthropic Claude')],
            ['value' => Config::PROVIDER_OPENAI, 'label' => __('OpenAI')],
            ['value' => Config::PROVIDER_GEMINI, 'label' => __('Google Gemini')],
        ];
    }
}
