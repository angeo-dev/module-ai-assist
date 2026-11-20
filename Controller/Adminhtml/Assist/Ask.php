<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Controller\Adminhtml\Assist;

use Angeo\AiAssist\Model\Assistant;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

/**
 * POST angeo_ai_assist/assist/ask — one answer as JSON.
 *
 * POST only, so Magento checks the form key (BackendValidator). ACL resource
 * Angeo_AiAssist::use. The owning module checks its own ACL again when it
 * builds the facts.
 */
class Ask extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_AiAssist::use';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Assistant $assistant,
        private readonly ResolverInterface $localeResolver,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $user   = $this->_auth->getUser();

        try {
            $answer = $this->assistant->ask(
                $this->param('source'),
                $this->param('ref'),
                $this->param('task'),
                $this->param('question'),
                $this->adminUserId($user),
                $this->language()
            );

            return $result->setData(['ok' => true] + $answer);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('[angeo_ai_assist] ' . $e->getMessage(), ['exception' => $e]);

            return $result->setData(['ok' => false, 'error' => (string) __('Something went wrong. Details are in the exception log.')]);
        }
    }

    /**
     * The admin user id. getUser() is typed as StorageInterface, which has no
     * getId(); the real object is Magento\User\Model\User (an AbstractModel).
     */
    private function adminUserId(mixed $user): int
    {
        return $user instanceof AbstractModel ? (int) $user->getId() : 0;
    }

    /** A request parameter as a string; arrays (question[]=…) become ''. */
    private function param(string $name): string
    {
        $value = $this->getRequest()->getParam($name, '');

        return is_scalar($value) ? (string) $value : '';
    }

    /** The admin's interface language, as a word the model understands. */
    private function language(): string
    {
        $locale = (string) $this->localeResolver->getLocale();
        if ($locale !== '' && class_exists(\Locale::class)) {
            $name = (string) \Locale::getDisplayLanguage($locale, 'en');
            if ($name !== '' && preg_match('/^[A-Za-z ()-]{2,40}$/', $name) === 1) {
                return $name;
            }
        }

        return 'English';
    }
}
