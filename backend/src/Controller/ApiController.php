<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Presenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class ApiController extends AbstractController
{
    public function __construct(protected readonly Presenter $presenter)
    {
    }

    protected function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
