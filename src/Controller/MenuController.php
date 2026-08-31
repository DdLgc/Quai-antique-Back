<?php
namespace App\Controller;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/menus', name: 'app_api_menu_')]
class MenuController extends AbstractController
{
    public function __construct(
        private MenuRepository $repository,
        private EntityManagerInterface $manager,
    ) {
    }

    #[Route(methods: ['GET'])]
    public function list(): JsonResponse
    {
        $menus = $this->repository->findAll();

        $data = array_map(
            static fn (Menu $menu) => [
                'id' => $menu->getId(),
                'name' => $menu->getName(),
                'description' => $menu->getDescription(),
                'price' => $menu->getPrice(),
                'dishes' => array_map(
                    static fn ($dish) => [
                        'id' => $dish->getId(),
                        'name' => $dish->getName(),
                        'description' => $dish->getDescription(),
                        'category' => $dish->getCategory(),
                    ],
                    $menu->getDishes()->toArray()
                ),
            ],
            $menus
        );

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = $request->toArray();

        if (
            empty($data['name']) ||
            !isset($data['price'])
        ) {
            return new JsonResponse(
                ['message' => 'Nom et prix obligatoires'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_numeric($data['price']) || (float) $data['price'] <= 0) {
            return new JsonResponse(
                ['message' => 'Prix invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $menu = (new Menu())
            ->setName(trim($data['name']))
            ->setDescription($data['description'] ?? null)
            ->setPrice((string) $data['price'])
            ->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($menu);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $menu->getId(),
                'name' => $menu->getName(),
                'description' => $menu->getDescription(),
                'price' => $menu->getPrice(),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $menu = $this->repository->find($id);

        if (!$menu) {
            return new JsonResponse(
                ['message' => 'Menu introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        if (isset($data['name'])) {
            $menu->setName(trim($data['name']));
        }

        if (array_key_exists('description', $data)) {
            $menu->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            if (!is_numeric($data['price']) || (float) $data['price'] <= 0) {
                return new JsonResponse(
                    ['message' => 'Prix invalide'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $menu->setPrice((string) $data['price']);
        }

        $menu->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse([
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'description' => $menu->getDescription(),
            'price' => $menu->getPrice(),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $menu = $this->repository->find($id);

        if (!$menu) {
            return new JsonResponse(
                ['message' => 'Menu introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->manager->remove($menu);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}