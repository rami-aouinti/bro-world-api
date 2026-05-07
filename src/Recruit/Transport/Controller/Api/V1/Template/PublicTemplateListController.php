<?php
declare(strict_types=1);
namespace App\Recruit\Transport\Controller\Api\V1\Template;
use App\Recruit\Infrastructure\Repository\TemplateRepository;
use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpFoundation\Request;use Symfony\Component\HttpKernel\Attribute\AsController;use Symfony\Component\Routing\Attribute\Route;
#[AsController] final readonly class PublicTemplateListController { public function __construct(private TemplateRepository $templateRepository){}
#[Route(path:'/v1/recruit/templates/resumes',methods:[Request::METHOD_GET])] public function resumes(): JsonResponse { return $this->list('resume'); }
#[Route(path:'/v1/recruit/templates/cover-pages',methods:[Request::METHOD_GET])] public function coverPages(): JsonResponse { return $this->list('cover_page'); }
#[Route(path:'/v1/recruit/templates/cover-letters',methods:[Request::METHOD_GET])] public function coverLetters(): JsonResponse { return $this->list('cover_letter'); }
private function list(string $type): JsonResponse { $rows=$this->templateRepository->findBy(['type'=>$type],['createdAt'=>'DESC']); return new JsonResponse(array_map(fn($t)=>['id'=>$t->getId(),'name'=>$t->getName(),'type'=>$t->getType(),'version'=>$t->getVersion(),'layout'=>$t->getLayout(),'structure'=>$t->getStructure(),'sections'=>$t->getSections(),'theme'=>$t->getTheme(),'aside'=>$t->getAside(),'photo'=>$t->getPhoto(),'decor'=>$t->getDecor(),'layoutOptions'=>$t->getLayoutOptions(),'decorOptions'=>$t->getDecorOptions(),'sectionTitleStyle'=>$t->getSectionTitleStyle(),'headerType'=>$t->getHeaderType(),'fakeData'=>$t->getFakeData(),'textStyles'=>$t->getTextStyles(),'typography'=>$t->getTypography(),'sectionBar'=>$t->getSectionBar(),'items'=>$t->getItems()],$rows)); }}
