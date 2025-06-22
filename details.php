<?php

session_start();
require_once('connect.php');

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $db->exec("SET NAMES 'utf8mb4'");

    $id = strip_tags($_GET['id']);

    $sql = '
        SELECT l.*, p.name AS production_company
        FROM liste l
        LEFT JOIN production_companies p ON l.production_company_id = p.id
        WHERE l.id = :id
    ';
    $query = $db->prepare($sql);
    $query->bindValue(':id', $id, PDO::PARAM_INT);
    $query->execute();

    $produit = $query->fetch();

    if (!$produit) {
        $_SESSION['erreur'] = "Cet id n'existe pas";
        header('Location: index.php');
        exit;
    }

    $isPrime = false;
    if (isset($_SESSION['id'])) {
        $userId = $_SESSION['id'];
        $sqlUser = 'SELECT is_prime FROM users WHERE id = :id';
        $queryUser = $db->prepare($sqlUser);
        $queryUser->bindValue(':id', $userId, PDO::PARAM_INT);
        $queryUser->execute();
        $user = $queryUser->fetch();

        if ($user) {
            $isPrime = (bool)$user['is_prime'];
        }
    }
    $prixOriginal = is_numeric(str_replace(',', '.', $produit['prix']))
        ? (float)str_replace(',', '.', $produit['prix'])
        : 0;
    $prixPromo = $prixOriginal;

    $quantities = [
        5 => ['class' => 'text-danger', 'text' => 'Stock faible: '],
        10 => ['class' => 'text-warning', 'text' => 'Stock moyen: '],
        PHP_INT_MAX => ['class' => 'text-success', 'text' => 'Stock élevé: ']
    ];

    $quantityClass = '';
    $quantityText = '';
    foreach ($quantities as $limit => $data) {
        if ($produit['nombre'] <= $limit) {
            $quantityClass = $data['class'];
            $quantityText = $data['text'] . $produit['nombre'];
            break;
        }
    }

    if (is_numeric($produit['Promo']) && $produit['Promo'] > 0) {
        $prixPromo *= (1 - $produit['Promo'] / 100);
    }

    $companyName = strtolower(trim($produit['production_company']));
    if ($isPrime && $companyName === 'amazon') {
        $prixPromo *= 0.9;
        $prixPromo = max($prixPromo, 0);
    }

    $sqlComments = '
        SELECT c.comment, c.rating, u.username, c.created_at
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.product_id = :id
        ORDER BY c.created_at DESC
    ';
    $queryComments = $db->prepare($sqlComments);
    $queryComments->bindValue(':id', $id, PDO::PARAM_INT);
    $queryComments->execute();
    $comments = $queryComments->fetchAll();

    $averageRating = 0;
    if (!empty($comments)) {
        $totalRating = array_sum(array_column($comments, 'rating'));
        $averageRating = $totalRating / count($comments);
    }

    $sqlRelated = '
        SELECT l.*, p.name AS production_company,
               (SELECT AVG(c.rating) FROM comments c WHERE c.product_id = l.id) AS average_rating
        FROM liste l
        LEFT JOIN production_companies p ON l.production_company_id = p.id
        WHERE l.id != :id
        ORDER BY RAND()
        LIMIT 4
    ';
    $queryRelated = $db->prepare($sqlRelated);
    $queryRelated->bindValue(':id', $id, PDO::PARAM_INT);
    $queryRelated->execute();
    $relatedProducts = $queryRelated->fetchAll();

    require_once('close.php');
} else {
    $_SESSION['erreur'] = "URL invalide";
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du produit</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Ubuntu', sans-serif;
        }

        .product-details {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            flex: 1;
            max-width: 500px;
            margin-right: 20px;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
        }

        .product-image img {
            border-radius: 10px;
            width: 100%;
            height: auto;
        }

        .product-info {
            flex: 2;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #343a40;
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 500;
            color: #ff9900;
            margin-bottom: 10px;
        }

        .average-rating {
            font-size: 1.2rem;
            font-weight: 500;
            color: #ffc107;
            margin-bottom: 20px;
        }

        .product-description {
            font-size: 1.2rem;
            font-weight: 400;
            margin-bottom: 20px;
            color: #6c757d;
            white-space: pre-wrap;
        }

        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }

        .btn-custom {
            flex: 1;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .btn-add-to-cart,
        .btn-back {
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-price-original {
            text-decoration: line-through;
            color: #6c757d;
            margin-right: 10px;
        }

        .card-price-promo {
            color: #ff9900;
            font-weight: bold;
        }

        .star-rating {
            direction: rtl;
            display: inline-flex;
        }

        .star-rating input[type="radio"] {
            display: none;
        }

        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
        }

        .star-rating input[type="radio"]:checked~label {
            color: #ffc107;
        }

        .star-rating label:hover,
        .star-rating label:hover~label {
            color: #ffc107;
        }

        .comment {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .comment .username {
            font-weight: 700;
            color: #343a40;
        }

        .comment .rating {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #ffc107;
        }

        .comment .text {
            margin-top: 10px;
            font-weight: 400;
            color: #6c757d;
        }

        .comment .date {
            margin-top: 10px;
            font-size: 0.9rem;
            font-weight: 400;
            color: #adb5bd;
            text-align: right;
        }

        .btn-submit {
            font-size: 1.2rem;
        }

        .btn-submit.disabled {
            background-color: #6c757d;
            border-color: #6c757d;
            cursor: not-allowed;
        }

        .comment-form {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-details,
        .comment-form,
        .comments-section,
        .related-products {
            margin-bottom: 20px;
        }

        .no-comments {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <main class="container">
        <div class="row">
            <section class="col-12">
                <div class="product-details">
                    <div class="product-image">
                        <img src="image_produit/<?= htmlspecialchars($produit['image_produit']) ?>" alt="<?= htmlspecialchars($produit['produit']) ?>" class="img-fluid">
                    </div>
                    <div class="product-info">
                        <h1 class="product-title"><?= htmlspecialchars($produit['produit']) ?></h1>
                        <p class="product-price">
                            <?php if ($prixPromo < $prixOriginal): ?>
                                <span class="card-price-original"><?= number_format($prixOriginal, 2) ?> €</span>
                                <span class="card-price-promo"><?= number_format($prixPromo, 2) ?> €</span>
                            <?php else: ?>
                                <?= number_format($prixOriginal, 2) ?> €
                            <?php endif; ?>
                        </p>
                        <p class="product-quantity <?= $quantityClass ?>"><?= htmlspecialchars($quantityText) ?></p>
                        <p class="average-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= $i <= round($averageRating) ? '★' : '☆' ?>
                            <?php endfor; ?>
                            (<?= number_format($averageRating, 1) ?>)
                        </p>
                        <p class="product-description"><?= nl2br(htmlspecialchars($produit['Description'])) ?></p>
                        <div class="btn-container">
                            <form method="post" action="cart.php">
                                <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                                <input type="hidden" name="quantite" value="1">
                                <button type="submit" class="btn btn-success btn-custom btn-add-to-cart">
                                    <i class="fas fa-shopping-cart" style="margin-right: 8px;"></i> Ajouter au panier
                                </button>
                            </form>
                            <a href="index.php" class="btn btn-primary btn-custom btn-back">Retour</a>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['id'])): ?>
                    <div class="comment-form">
                        <h2>Laisser un commentaire</h2>
                        <form method="post" action="submit_comment.php" id="commentForm">
                            <input type="hidden" name="product_id" value="<?= $produit['id'] ?>">
                            <div class="form-group">
                                <label for="rating">Noter :</label>
                                <div id="rating" class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                        <label for="star<?= $i ?>" title="<?= $i ?> étoiles">&#9733;</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="comment">Commentaire :</label>
                                <textarea name="comment" id="comment" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-success btn-submit" id="submitBtn">Envoyer</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="comment-form">
                        <h2>Laisser un commentaire</h2>
                        <p>Vous devez être connecté pour laisser un commentaire.</p>
                        <button class="btn btn-secondary btn-submit disabled" disabled>Envoyer</button>
                    </div>
                <?php endif; ?>

                <div class="comments-section">
                    <h2>Commentaires</h2>
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <span class="username"><?= htmlspecialchars($comment['username']) ?></span>
                                <span class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= $comment['rating'] ? '★' : '☆' ?>
                                    <?php endfor; ?>
                                </span>
                                <p class="text"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                <p class="date">Posté le <?= date('d/m/Y à H:i', strtotime($comment['created_at'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-comments">
                            <p>Aucun commentaire pour ce produit.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="related-products">
                    <h2>Produits similaires</h2>
                    <div class="row">
                        <?php foreach ($relatedProducts as $related): ?>
                            <div class="col-md-3">
                                <div class="card mb-4 shadow-sm">
                                    <img src="image_produit/<?= htmlspecialchars($related['image_produit']) ?>" class="card-img-top" alt="<?= htmlspecialchars($related['produit']) ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($related['produit']) ?></h5>
                                        <p class="card-text">À seulement : <?= number_format((float)str_replace(',', '.', $related['prix']), 2) ?> €</p>
                                        <p class="card-text">Quantité restante: <?= htmlspecialchars($related['nombre']) ?></p>
                                        <p class="card-text">
                                            Note moyenne:
                                            <?php
                                            $averageRating = $related['average_rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++):
                                            ?>
                                                <span style="color: #ffc107;"><?= $i <= round($averageRating) ? '★' : '☆' ?></span>
                                            <?php endfor; ?>
                                            (<?= number_format($averageRating, 1) ?>)
                                        </p>
                                        <a href="details.php?id=<?= $related['id'] ?>" class="btn btn-primary">Voir le produit</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('commentForm');
            const submitBtn = document.getElementById('submitBtn');
            const ratingInputs = form.querySelectorAll('input[name="rating"]');
            const commentInput = form.querySelector('textarea[name="comment"]');

            function checkFormValidity() {
                let isValid = false;
                ratingInputs.forEach(input => {
                    if (input.checked) {
                        isValid = true;
                    }
                });
                if (commentInput.value.trim() === '') {
                    isValid = false;
                }
                submitBtn.disabled = !isValid;
                submitBtn.classList.toggle('btn-success', isValid);
                submitBtn.classList.toggle('btn-secondary', !isValid);
            }

            form.addEventListener('input', checkFormValidity);
            checkFormValidity();
        });
    </script>
</body>

</html>