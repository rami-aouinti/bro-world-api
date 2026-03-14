<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Offer;
use App\Recruit\Domain\Entity\OfferStatusHistory;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\OfferStatus;
use App\Recruit\Infrastructure\Repository\ApplicationStatusHistoryRepository;
use App\Recruit\Infrastructure\Repository\OfferRepository;
use App\Recruit\Infrastructure\Repository\OfferStatusHistoryRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use ValueError;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_numeric;
use function is_string;
use function mb_strlen;
use function strtoupper;

readonly class OfferWorkflowService
{
    public function __construct(
        private OfferRepository $offerRepository,
        private OfferStatusHistoryRepository $offerStatusHistoryRepository,
        private ApplicationStatusHistoryRepository $applicationStatusHistoryRepository,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function create(Application $application, array $payload, User $author): Offer
    {
        if ($application->getStatus() === ApplicationStatus::HIRED || $application->getStatus() === ApplicationStatus::REJECTED) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Cannot create an offer for a closed application.');
        }

        $salary = $payload['salaryProposed'] ?? null;
        if (!is_numeric($salary)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "salaryProposed" must be numeric.');
        }

        $startDate = $payload['startDate'] ?? null;
        if (!is_string($startDate)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "startDate" must be a string with format Y-m-d.');
        }

        $parsedStartDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
        if (!$parsedStartDate instanceof DateTimeImmutable) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "startDate" must use format Y-m-d.');
        }

        $contractType = $payload['contractType'] ?? null;
        if (!is_string($contractType)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "contractType" must be a string.');
        }

        $comment = is_string($payload['comment'] ?? null) ? $payload['comment'] : null;

        try {
            $parsedContractType = ContractType::from($contractType);
        } catch (ValueError) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "contractType" must be one of: CDI, CDD, Freelance, Internship.');
        }

        $offer = (new Offer())
            ->setApplication($application)
            ->setSalaryProposed((float) $salary)
            ->setStartDate($parsedStartDate)
            ->setContractType($parsedContractType)
            ->setStatus(OfferStatus::DRAFT);

        $this->offerRepository->save($offer, false);
        $this->logOfferAction($offer, $author, 'created', OfferStatus::DRAFT, OfferStatus::DRAFT, $comment);

        return $offer;
    }

    public function send(Offer $offer, User $author, ?string $comment = null): void
    {
        $this->transitionOffer($offer, $author, 'sent', OfferStatus::SENT, $comment);
    }

    public function accept(Offer $offer, User $author, ?string $comment = null): void
    {
        $this->transitionOffer($offer, $author, 'accepted', OfferStatus::ACCEPTED, $comment);

        $application = $offer->getApplication();
        $fromStatus = $application->getStatus();

        if ($fromStatus !== ApplicationStatus::OFFER_SENT) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Application must be in OFFER_SENT status before accepting an offer.');
        }

        $application->setStatus(ApplicationStatus::HIRED);
        $this->applicationStatusHistoryRepository->save((new \App\Recruit\Domain\Entity\ApplicationStatusHistory())
            ->setApplication($application)
            ->setAuthor($author)
            ->setFromStatus($fromStatus)
            ->setToStatus(ApplicationStatus::HIRED)
            ->setComment($comment), false);
    }

    public function decline(Offer $offer, User $author, ?string $comment = null): void
    {
        $this->transitionOffer($offer, $author, 'declined', OfferStatus::DECLINED, $comment);

        $application = $offer->getApplication();
        $fromStatus = $application->getStatus();

        if ($fromStatus !== ApplicationStatus::OFFER_SENT) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Application must be in OFFER_SENT status before declining an offer.');
        }

        $application->setStatus(ApplicationStatus::REJECTED);
        $this->applicationStatusHistoryRepository->save((new \App\Recruit\Domain\Entity\ApplicationStatusHistory())
            ->setApplication($application)
            ->setAuthor($author)
            ->setFromStatus($fromStatus)
            ->setToStatus(ApplicationStatus::REJECTED)
            ->setComment($comment), false);
    }

    public function withdraw(Offer $offer, User $author, ?string $comment = null): void
    {
        $this->transitionOffer($offer, $author, 'withdrawn', OfferStatus::WITHDRAWN, $comment);
    }

    private function transitionOffer(Offer $offer, User $author, string $action, OfferStatus $toStatus, ?string $comment): void
    {
        $fromStatus = $offer->getStatus();
        if (!$this->isAllowedTransition($fromStatus, $toStatus)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Cannot transition offer status from ' . $fromStatus->value . ' to ' . $toStatus->value . '.');
        }

        $offer->setStatus($toStatus);
        $this->logOfferAction($offer, $author, $action, $fromStatus, $toStatus, $comment);

        if ($toStatus === OfferStatus::SENT) {
            $offer->getApplication()->setStatus(ApplicationStatus::OFFER_SENT);
        }
    }

    private function isAllowedTransition(OfferStatus $from, OfferStatus $to): bool
    {
        return match ($from) {
            OfferStatus::DRAFT => $to === OfferStatus::SENT || $to === OfferStatus::WITHDRAWN,
            OfferStatus::SENT => $to === OfferStatus::ACCEPTED || $to === OfferStatus::DECLINED || $to === OfferStatus::WITHDRAWN,
            OfferStatus::ACCEPTED, OfferStatus::DECLINED, OfferStatus::WITHDRAWN => false,
        };
    }

    private function logOfferAction(Offer $offer, User $author, string $action, OfferStatus $fromStatus, OfferStatus $toStatus, ?string $comment): void
    {
        $this->offerStatusHistoryRepository->save((new OfferStatusHistory())
            ->setOffer($offer)
            ->setAuthor($author)
            ->setAction($this->sanitizeAction($action))
            ->setFromStatus($fromStatus)
            ->setToStatus($toStatus)
            ->setComment($comment), false);
    }

    private function sanitizeAction(string $action): string
    {
        $action = strtoupper($action);

        return mb_strlen($action) > 50 ? 'UPDATED' : $action;
    }
}
