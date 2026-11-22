<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\WebhookService;

final class WebhookController
{
    public function __construct(private readonly WebhookService $servico)
    {
    }

    /** @param array<string, string> $parametros */
    public function infinitepay(Request $requisicao, array $parametros): Response
    {
        // Content-Type livre: um 415 faria a notificação ser repetida e perdida
        return Response::ok($this->servico->processar($requisicao->json(false)));
    }
}