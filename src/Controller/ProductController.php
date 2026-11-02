<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

// L'attribut #[Route] au niveau de la classe définit un préfixe d'URL.
// Toutes les routes de ce contrôleur commenceront par /api/products
// C'est l'équivalent de router.use('/api/products', productRouter) dans Express.
#[Route('/api/products', name: 'api_products_')]
class ProductController extends AbstractController
{
    // ==========================================
    // 1. READ - Récupérer la liste des produits
    // ==========================================
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): JsonResponse
    {
        // ProductRepository est un service fourni par Doctrine pour faire des requêtes SELECT (comme Model.find() en Mongoose)
        $products = $productRepository->findAll();

        // On transforme nos objets PHP en tableau associatif (équivalent d'un tableau d'objets JSON)
        // car PHP ne peut pas convertir un objet complexe directement en JSON sans aide.
        $data =array_map(function(Product $product){
            return[
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'imageUrl' => $product->getImageUrl(),
                // On récupère le nom de la catégorie si elle existe grâce à l'opérateur de null-safe (?->)
                'category' => $product->getCategory()?->getName(),
            ];
        }, $products);

        // $this->json() est un raccourci de Symfony pour renvoyer une JsonResponse (équivalent de res.json() dans Express)
        return $this->json($data);
    
    }
    // ==========================================
    // 2. CREATE - Ajouter un nouveau produit
    // ==========================================
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Request $request permet de récupérer les données entrantes (équivalent de req.body dans Express)
        // On décode le JSON brut envoyé par le front-end Next.js
        $data = json_decode($request->getContent(), true);

        // Si le JSON est mal formé ou vide
        if ($data === null) {
            return $newResponse = new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // On instancie une nouvelle entité vide (équivalent de `new Product({...})` en Mongoose)
        $product = new Product();
        $product->setName($data['name'] ?? '');
        $product->setDescription($data['description'] ?? null);
        $product->setPrice(isset($data['price']) ? (string) $data['price']:'0');
        $product->setStock($data['stock'] ?? 0);
        $product->setImageUrl($data['imageUrl'] ?? null);
        $product->setCreatedAt(new \DateTimeImmutable());

        // EntityManagerInterface est le service Doctrine qui gère les écritures en base (INSERT, UPDATE, DELETE).
        // `persist` prépare l'objet pour l'insertion (il le met dans le "panier" de Doctrine).
        $entityManager->persist($product);
        
        // `flush` exécute la requête SQL (INSERT INTO) dans la base de données (équivalent de .save() en Mongoose).
        $entityManager->flush();

        // On retourne une réponse avec un code HTTP 201 (Created)
        return $this->json([
            'message' => 'Produit créé avec succès !',
            'id' => $product->getId()
        ], JsonResponse::HTTP_CREATED);
    }

    // ==========================================
    // 3. DELETE - Supprimer un produit
    // ==========================================
    // Note l'utilisation de {id} dans la route, c'est un paramètre dynamique (ex: /api/products/5)
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        // MAGIE DE SYMFONY : Grâce au type hinting `Product $product`, Symfony lit l'id dans l'URL, 
        // va chercher automatiquement le produit en base de données, et s'il n'existe pas, 
        // il renvoie directement une erreur 404 tout seul ! (Plus besoin de faire un findById et un if(!product) à la main).

        // On demande à l'EntityManager de supprimer l'entité
        $entityManager->remove($product);
        $entityManager->flush(); // Exécute le DELETE SQL

        return $this->json(['message' => 'Produit supprimé avec succès']);
    }

    // ==========================================
    // 4. UPDATE - Mettre à jour un produit
    // ==========================================
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Product $product, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // 1. On récupère les données envoyée par le front-end (équivalent de req.body en Express)
        $data = json_decode($request->getContent(), true);

        // 2. On met à jour les propriétés du produit si elles sont présentes dans la requête
        // L'opérateur ?? (null coalescing) ou isset() permet de gérer les mises à jour partielles (PATCH)
        if (isset($data['name'])) {
            $product->setName($data['name']);
        }
        // array_key_exist vérifie si la clé existe dans le tableau
        if (array_key_exists('description', $data)){
            $product->setDescription($data['description']); //vide la description si on envoie null
        }
        // issert sert à  vérifié si la clé existe et que sa valeur n'est pas null
        if(isset($data['price'])){
            $product->setPrice((string) $data['price']); // Conversion explicite en string
        }
        if(isset($data['stock'])){
            $product->setStock($data['stock']);
        }
        if(isset($data['imageUrl'])){
            $product->setImageUrl($data['imageUrl']);
        }

        // 3. PAS BESOIN de faire de $entityManager->persist($product) ici !
        // EXplication juste en dessous 👇

        // 4. On valide les changements en base de données
        // `flush()` analyse les objets "suivis" par Doctrine, détecte les modifications et génère le SQL UPDATE.
        $entityManager->flush();

        return $this->json([
            'message' => 'Produit mis à jour avec succès !',
            'id' => $product->getId()
        ]);
    }

}
