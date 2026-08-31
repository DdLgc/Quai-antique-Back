<?php

namespace App\Controller;

use App\Entity\Dish;
use App\Repository\DishRepository;
use App\Repository\MenuRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dishes', name: 'app_api_dish_')]
class DishController extends AbstractController
{
    public function __construct(
        private DishRepository $repository,
        private MenuRepository $menuRepository,
        private EntityManagerInterface $manager,
    ) {
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = $request->toArray();

        if (
            empty($data['name']) ||
            empty($data['category']) ||
            !isset($data['menuId'])
        ) {
            return new JsonResponse(
                ['message' => 'Nom, catégorie et menu obligatoires'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $allowedCategories = [
            'starter',
            'main',
            'dessert',
        ];

        if (!in_array($data['category'], $allowedCategories, true)) {
            return new JsonResponse(
                ['message' => 'Catégorie invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $menu = $this->menuRepository->find((int) $data['menuId']);

        if (!$menu) {
            return new JsonResponse(
                ['message' => 'Menu introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $dish = (new Dish())
            ->setName(trim($data['name']))
            ->setDescription($data['description'] ?? null)
            ->setCategory($data['category'])
            ->setMenu($menu)
            ->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($dish);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $dish->getId(),
                'name' => $dish->getName(),
                'description' => $dish->getDescription(),
                'category' => $dish->getCategory(),
                'menuId' => $dish->getMenu()?->getId(),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $dish = $this->repository->find($id);

        if (!$dish) {
            return new JsonResponse(
                ['message' => 'Plat introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        if (isset($data['name'])) {
            $dish->setName(trim($data['name']));
        }

        if (array_key_exists('description', $data)) {
            $dish->setDescription($data['description']);
        }

        if (isset($data['category'])) {
            $allowedCategories = [
                'starter',
                'main',
                'dessert',
            ];

            if (!in_array($data['category'], $allowedCategories, true)) {
                return new JsonResponse(
                    ['message' => 'Catégorie invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $dish->setCategory($data['category']);
        }

        if (isset($data['menuId'])) {
            $menu = $this->menuRepository->find((int) $data['menuId']);

            if (!$menu) {
                return new JsonResponse(
                    ['message' => 'Menu introuvable'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $dish->setMenu($menu);
        }

        $dish->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $dish->getId(),
                'name' => $dish->getName(),
                'description' => $dish->getDescription(),
                'category' => $dish->getCategory(),
                'menuId' => $dish->getMenu()?->getId(),
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $dish = $this->repository->find($id);

        if (!$dish) {
            return new JsonResponse(
                ['message' => 'Plat introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->manager->remove($dish);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
