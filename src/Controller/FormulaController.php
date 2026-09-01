<?php


namespace App\Controller;

use App\Entity\Formula;
use App\Repository\FormulaRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/formulas', name: 'app_api_formula_')]
class FormulaController extends AbstractController
{
    public function __construct(
        private FormulaRepository      $repository,
        private MenuRepository         $menuRepository,
        private EntityManagerInterface $manager,
    )
    {
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->toArray();

        if (!isset($data['description'], $data['price'], $data['menuId'])) {
            return new JsonResponse(
                ['message' => 'Description, prix et menu obligatoires'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_numeric($data['price']) || (float)$data['price'] <= 0) {
            return new JsonResponse(
                ['message' => 'Prix invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $menu = $this->menuRepository->find((int)$data['menuId']);

        if (!$menu) {
            return new JsonResponse(
                ['message' => 'Menu introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $formula = (new Formula())
            ->setDescription(trim($data['description']))
            ->setPrice((string)$data['price'])
            ->setMenu($menu);

        $this->manager->persist($formula);
        $this->manager->flush();

        return new JsonResponse([
            'id' => $formula->getId(),
            'description' => $formula->getDescription(),
            'price' => $formula->getPrice(),
            'menuId' => $menu->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $formula = $this->repository->find($id);

        if (!$formula) {
            return new JsonResponse(
                ['message' => 'Formule introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        if (isset($data['description'])) {
            $formula->setDescription(trim($data['description']));
        }

        if (isset($data['price'])) {
            if (!is_numeric($data['price']) || (float)$data['price'] <= 0) {
                return new JsonResponse(
                    ['message' => 'Prix invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $formula->setPrice((string)$data['price']);
        }

        $this->manager->flush();

        return new JsonResponse([
            'id' => $formula->getId(),
            'description' => $formula->getDescription(),
            'price' => $formula->getPrice(),
            'menuId' => $formula->getMenu()?->getId(),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $formula = $this->repository->find($id);

        if (!$formula) {
            return new JsonResponse(
                ['message' => 'Formule introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->manager->remove($formula);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}