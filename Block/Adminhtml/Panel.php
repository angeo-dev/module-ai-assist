<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Block\Adminhtml;

use Angeo\AiAssist\Model\Config;
use Angeo\AiAssist\Model\ContextCollector;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * The "Ask the AI assistant" panel.
 *
 * Added to other modules' pages by this module's own layout files, so those
 * modules do not depend on it. Layout arguments:
 *  - source:    the source code the owning module's observer answers to;
 *  - ref_param: the request parameter that holds the record id (optional);
 *  - ref_params: comma-separated filter parameters for pages that show a
 *               filtered report rather than one record ("period,from,to,store");
 *               the ref becomes "from=…&period=…&store=…" (sorted, present only);
 *  - ref_event: set to "1" when the page sets the record later in the
 *               browser (the panel then waits for "angeo-ai-assist:ref").
 */
class Panel extends Template
{
    /** @var string */
    protected $_template = 'Angeo_AiAssist::panel.phtml';

    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Angeo_AiAssist::use');
    }

    public function isReady(): bool
    {
        return $this->config->isReady();
    }

    public function getSource(): string
    {
        $source = (string) $this->getData('source');

        return ContextCollector::validSource($source) ? $source : '';
    }

    public function getRef(): string
    {
        $list = (string) $this->getData('ref_params');
        if ($list !== '') {
            $pairs = [];
            foreach (array_filter(array_map('trim', explode(',', $list))) as $name) {
                $value = $this->getRequest()->getParam($name);
                if (is_scalar($value) && (string) $value !== '' && preg_match('/^[A-Za-z0-9_.\-]{1,20}$/', (string) $value) === 1) {
                    $pairs[$name] = (string) $value;
                }
            }
            ksort($pairs);
            $ref = $pairs === [] ? 'default' : http_build_query($pairs);

            return ContextCollector::validRef($ref) ? $ref : 'default';
        }

        $param = (string) $this->getData('ref_param');
        if ($param === '') {
            return '';
        }
        $ref = (string) $this->getRequest()->getParam($param, '');

        return ContextCollector::validRef($ref) ? $ref : '';
    }

    public function waitsForRef(): bool
    {
        return (string) $this->getData('ref_event') === '1';
    }

    public function getAskUrl(): string
    {
        return $this->getUrl('angeo_ai_assist/assist/ask');
    }

    public function canConfigure(): bool
    {
        return $this->_authorization->isAllowed('Angeo_AiAssist::config');
    }

    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'angeo_ai_assist']);
    }

    public function getProviderLabel(): string
    {
        return $this->config->getProviderLabel();
    }

    public function getModel(): string
    {
        return $this->config->getModel();
    }

    /**
     * @return array<string,mixed>
     */
    public function getJsConfig(): array
    {
        return [
            'url'       => $this->getAskUrl(),
            'source'    => $this->getSource(),
            'ref'       => $this->getRef(),
            'waitsForRef' => $this->waitsForRef(),
        ];
    }

    protected function _toHtml(): string
    {
        if ($this->getSource() === '' || !$this->isAllowed()) {
            return '';
        }

        return parent::_toHtml();
    }
}
