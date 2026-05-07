<?php
declare(strict_types=1);
namespace App\Recruit\Transport\Controller\Api\V1\Resume;
use App\Recruit\Application\Service\ResumeNormalizerService;use App\Recruit\Domain\Entity\Resume;use App\Recruit\Infrastructure\Repository\ResumeRepository;use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpFoundation\Request;use Symfony\Component\HttpKernel\Attribute\AsController;use Symfony\Component\HttpKernel\Exception\HttpException;use Symfony\Component\Routing\Attribute\Route;
#[AsController] final readonly class PublicActiveResumeByUsernameController {
    public function __construct(
        private UserRepository $userRepository,
        private ResumeRepository $resumeRepository,
        private ResumeNormalizerService $normalizer)
    {}

    /**
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws ORMException
     */
    #[Route(path:'/v1/recruit/resumes/active/{username}',methods:[Request::METHOD_GET])]
public function __invoke(string $username): JsonResponse{
    $user=$this->userRepository->loadUserByIdentifier($username, false);
    if($user===null){ throw new HttpException(404,'User not found.');
    }
    $resumes=$this->resumeRepository->findBy(['owner'=>$user,'isActive'=>true],['createdAt'=>'DESC'],1);
    $resume=$resumes[0]??null;
    if(!$resume instanceof Resume)
    { throw new HttpException(404,'Active resume not found.'); }
    return new JsonResponse($this->normalizer->normalize($resume));
}}
