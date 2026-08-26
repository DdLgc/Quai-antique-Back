<?php

namespace App\Controller;

use App\Entity\Picture;
use App\Repository\PictureRepository;
use App\Service\Utils;
use App\Repository\RestaurantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/pictures', name: 'app_api_picture_')]
class PictureController extends AbstractController
{
    public function __construct(
        private PictureRepository      $repository,
        private RestaurantRepository   $restaurantRepository,
        private EntityManagerInterface $manager,
    )
    {
    }

    #[Route(methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pictures = $this->repository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        $data = array_map(
            static fn(Picture $picture) => [
                'id' => $picture->getId(),
                'title' => $picture->getTitle(),
                'imageUrl' => sprintf(
                    '%s/uploads/gallery/%s',
                    $request->getSchemeAndHttpHost(),
                    $picture->getImageName()
                ),
            ],
            $pictures
        );

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $title = trim((string)$request->request->get('title'));

        /** @var UploadedFile|null $image */
        $image = $request->files->get('image');

        if ($title === '' || !$image) {
            return new JsonResponse(
                ['message' => 'Le titre et l’image sont obligatoires'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$image->isValid()) {
            return new JsonResponse(
                [
                    'message' => 'Le fichier envoyé est invalide',
                    'uploadError' => $image->getErrorMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!in_array($image->getMimeType(), $allowedMimeTypes, true)) {
            return new JsonResponse(
                ['message' => 'Format d’image non autorisé'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $restaurant = $this->restaurantRepository->find(1);

        if (!$restaurant) {
            return new JsonResponse(
                ['message' => 'Restaurant introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $extension = $image->guessExtension() ?: 'jpg';

        $imageName = sprintf(
            '%s-%s.%s',
            Utils::slugify($title),
            bin2hex(random_bytes(4)),
            $extension
        );

        $image->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads/gallery',
            $imageName
        );

        $picture = (new Picture())
            ->setTitle($title)
            ->setSlug(Utils::slugify($title))
            ->setImageName($imageName)
            ->setRestaurant($restaurant)
            ->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($picture);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $picture->getId(),
                'title' => $picture->getTitle(),
                'imageUrl' => sprintf(
                    '%s/uploads/gallery/%s',
                    $request->getSchemeAndHttpHost(),
                    $picture->getImageName()
                ),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $picture = $this->repository->find($id);

        if (!$picture) {
            return new JsonResponse(
                ['message' => 'Image introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $imagePath = sprintf(
            '%s/public/uploads/gallery/%s',
            $this->getParameter('kernel.project_dir'),
            $picture->getImageName()
        );

        if (is_file($imagePath)) {
            unlink($imagePath);
        }

        $this->manager->remove($picture);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'edit', methods: ['POST'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $picture = $this->repository->find($id);

        if (!$picture) {
            return new JsonResponse(
                ['message' => 'Image introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $title = trim((string)$request->request->get('title'));

        /** @var UploadedFile|null $image */
        $image = $request->files->get('image');

        if ($title !== '') {
            $picture
                ->setTitle($title)
                ->setSlug(Utils::slugify($title));
        }

        if ($image) {
            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'image/webp',
            ];

            if (!in_array($image->getMimeType(), $allowedMimeTypes, true)) {
                return new JsonResponse(
                    ['message' => 'Format d’image non autorisé'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $oldImagePath = sprintf(
                '%s/public/uploads/gallery/%s',
                $this->getParameter('kernel.project_dir'),
                $picture->getImageName()
            );

            $extension = $image->guessExtension() ?: 'jpg';

            $imageName = sprintf(
                '%s-%s.%s',
                Utils::slugify($title !== '' ? $title : $picture->getTitle()),
                bin2hex(random_bytes(4)),
                $extension
            );

            $image->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/gallery',
                $imageName
            );

            if (is_file($oldImagePath)) {
                unlink($oldImagePath);
            }

            $picture->setImageName($imageName);
        }

        $picture->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $picture->getId(),
                'title' => $picture->getTitle(),
                'imageUrl' => sprintf(
                    '%s/uploads/gallery/%s',
                    $request->getSchemeAndHttpHost(),
                    $picture->getImageName()
                ),
            ],
            Response::HTTP_OK
        );
    }
}