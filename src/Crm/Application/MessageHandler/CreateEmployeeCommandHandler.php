<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\CreateEmployeeCommand;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Employee;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\General\Application\Message\EntityCreated;
use App\User\Domain\Repository\Interfaces\UserRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateEmployeeCommandHandler
{
    public function __construct(
        private CrmRepository $crmRepository,
        private EmployeeRepository $employeeRepository,
        private UserRepositoryInterface $userRepository,
        private MessageBusInterface $messageBus,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateEmployeeCommand $command): void
    {
        $crm = $this->crmRepository->find($command->crmId);
        if ($crm === null) {
            return;
        }

        $user = $command->userId !== null ? $this->userRepository->find($command->userId) : null;

        $employee = new Employee()
            ->setId($command->id)
            ->setCrm($crm)
            ->setFirstName($command->firstName)
            ->setLastName($command->lastName)
            ->setEmail($command->email)
            ->setPositionName($command->positionName)
            ->setRoleName($command->roleName)
            ->setUser($user);

        $this->employeeRepository->save($employee);

        $this->messageBus->dispatch(new EntityCreated('crm_employee', $employee->getId(), context: [
            'applicationSlug' => $command->applicationSlug,
            'crmId' => $command->crmId,
        ]));

        $this->cacheInvalidator->invalidateEmployee($command->applicationSlug, $employee->getId());
    }
}
