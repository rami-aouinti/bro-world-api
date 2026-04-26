<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Transport\EventListener;

use App\Crm\Domain\Entity\Employee;
use App\Crm\Transport\EventListener\CrmEmployeeEntityEventListener;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;

final class CrmEmployeeEntityEventListenerTest extends TestCase
{
    public function testPrePersistCopiesMissingNamesFromLinkedUser(): void
    {
        $listener = new CrmEmployeeEntityEventListener();

        $user = (new User())
            ->setFirstName('John')
            ->setLastName('Doe');

        $employee = (new Employee())
            ->setFirstName('')
            ->setLastName('   ')
            ->setUser($user);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($employee);

        $listener->prePersist($event);

        self::assertSame('John', $employee->getFirstName());
        self::assertSame('Doe', $employee->getLastName());
    }

    public function testPrePersistDoesNotOverwriteExistingNames(): void
    {
        $listener = new CrmEmployeeEntityEventListener();

        $user = (new User())
            ->setFirstName('John')
            ->setLastName('Doe');

        $employee = (new Employee())
            ->setFirstName('Jane')
            ->setLastName('Smith')
            ->setUser($user);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($employee);

        $listener->prePersist($event);

        self::assertSame('Jane', $employee->getFirstName());
        self::assertSame('Smith', $employee->getLastName());
    }

    public function testPreUpdateSkipsWhenUserIsMissing(): void
    {
        $listener = new CrmEmployeeEntityEventListener();

        $employee = (new Employee())
            ->setFirstName('')
            ->setLastName('');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getUnitOfWork');

        $event = new PreUpdateEventArgs($employee, $entityManager, []);

        $listener->preUpdate($event);

        self::assertSame('', $employee->getFirstName());
        self::assertSame('', $employee->getLastName());
    }

    public function testPreUpdateCopiesMissingNamesAndRecomputesChangeSet(): void
    {
        $listener = new CrmEmployeeEntityEventListener();

        $user = (new User())
            ->setFirstName('John')
            ->setLastName('Doe');

        $employee = (new Employee())
            ->setFirstName('')
            ->setLastName('')
            ->setUser($user);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('recomputeSingleEntityChangeSet')
            ->with(
                self::isInstanceOf(ClassMetadata::class),
                self::identicalTo($employee),
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->with(Employee::class)->willReturn(new ClassMetadata(Employee::class));

        $event = new PreUpdateEventArgs($employee, $entityManager, []);

        $listener->preUpdate($event);

        self::assertSame('John', $employee->getFirstName());
        self::assertSame('Doe', $employee->getLastName());
    }
}
