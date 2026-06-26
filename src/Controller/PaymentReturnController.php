<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

namespace Coinsnap\Shopware\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Proxies the short redirect URL handed to the payment gateway back to the
 * long, tokenized Shopware finalize URL stored on the transaction. Needed
 * because Coinsnap caps the gateway redirectUrl at 255 characters.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class PaymentReturnController extends AbstractController
{
    private EntityRepository $orderTransactionRepository;

    public function __construct(EntityRepository $orderTransactionRepository)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    #[Route(path: '/coinsnap/payment-return/{transactionId}', name: 'frontend.coinsnap.payment_return', methods: ['GET'])]
    public function paymentReturn(string $transactionId, Request $request): RedirectResponse
    {
        $fallback = $request->getSchemeAndHttpHost();

        $transaction = $this->orderTransactionRepository
            ->search(new Criteria([$transactionId]), Context::createDefaultContext())
            ->first();

        if (!$transaction instanceof OrderTransactionEntity) {
            return new RedirectResponse($fallback);
        }

        $returnUrl = ($transaction->getCustomFields() ?? [])['coinsnapReturnUrl'] ?? null;
        if (!is_string($returnUrl) || $returnUrl === '') {
            return new RedirectResponse($fallback);
        }

        // Only redirect to the host we are served from, to rule out open redirects.
        $target = parse_url($returnUrl);
        if (empty($target['host']) || !hash_equals($request->getHost(), (string) $target['host'])) {
            return new RedirectResponse($fallback);
        }

        // Consume the stored URL so the finalize token can't be replayed via this route.
        $this->orderTransactionRepository->update([[
            'id' => $transaction->getId(),
            'customFields' => ['coinsnapReturnUrl' => null],
        ]], Context::createDefaultContext());

        return new RedirectResponse($returnUrl);
    }
}
