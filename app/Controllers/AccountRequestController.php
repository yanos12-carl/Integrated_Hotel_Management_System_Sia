<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response};
use App\Services\UserService;

final class AccountRequestController extends BaseController
{
    public function create(Request $r, array $p = []): Response
    {
        $id = UserService::requestAccount($r->body, $this->uid($r));
        return Response::created(['approval_id' => $id], 'Account request sent for administrator approval.');
    }
}
