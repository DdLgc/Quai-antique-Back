<?php

namespace App\Controller;

use App\Entity\Dish;
use App\Repository\DishRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dishes', name: 'app_api_dish_')]
class DishController extends AbstractController
{
    private const CATEGORIES = ['starter', 'main', 'dessert'];

    public function __construct(
        private DishRepository $repository,
        private EntityManagerInterface $manager,
    ) {
    }

    #[Route(methods: ['GET'])]
    public function list(): JsonResponse
    {
        $dishes = $this->repository->findAll();

        $data = array_map(
            static fn (Dish $dish) => [
                'id' => $dish->getId(),
                'name' => $dish->getName(),
                'description' => $dish->getDescription(),
                'category' => $dish->getCategory(),
                'price' => $dish->getPrice(),
            ],
            $dishes
        );

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->toArray();

        if (empty($data['name']) || empty($data['category']) || !isset($data['price'])) {
            return new JsonResponse(
                ['message' => 'Nom, catégorie et prix obligatoires'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($data['category'], self::CATEGORIES, true)) {
            return new JsonResponse(
                ['message' => 'Catégorie invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_numeric($data['price']) || (float) $data['price'] <= 0) {
            return new JsonResponse(
                ['message' => 'Prix invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $dish = (new Dish())
            ->setName(trim($data['name']))
            ->setDescription($data['description'] ?? null)
            ->setCategory($data['category'])
            ->setPrice((string) $data['price'])
            ->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($dish);
        $this->manager->flush();

        return new JsonResponse([
            'id' => $dish->getId(),
            'name' => $dish->getName(),
            'description' => $dish->getDescription(),
            'category' => $dish->getCategory(),
            'price' => $dish->getPrice(),
        ], Response::HTTP_CREATED);
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
            if (!in_array($data['category'], self::CATEGORIES, true)) {
                return new JsonResponse(
                    ['message' => 'Catégorie invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $dish->setCategory($data['category']);
        }

        if (isset($data['price'])) {
            if (!is_numeric($data['price']) || (float) $data['price'] <= 0) {
                return new JsonResponse(
                    ['message' => 'Prix invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $dish->setPrice((string) $data['price']);
        }

        $dish->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse([
            'id' => $dish->getId(),
            'name' => $dish->getName(),
            'description' => $dish->getDescription(),
            'category' => $dish->getCategory(),
            'price' => $dish->getPrice(),
        ], Response::HTTP_OK);
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