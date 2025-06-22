<?php
session_start();

if ($_POST) {
    require_once('connect.php');

    $produit = isset($_POST['produit']) ? strip_tags($_POST['produit']) : '';
    $description = isset($_POST['description']) ? strip_tags($_POST['description']) : '';
    $prix = isset($_POST['prix']) && is_numeric($_POST['prix']) ? strip_tags($_POST['prix']) : 0;
    $nombre = isset($_POST['nombre']) && is_numeric($_POST['nombre']) ? strip_tags($_POST['nombre']) : 0;
    $promo = isset($_POST['Promo']) && is_numeric($_POST['Promo']) ? strip_tags($_POST['Promo']) : 0;
    $production_company_id = isset($_POST['production_company_id']) && is_numeric($_POST['production_company_id']) ? strip_tags($_POST['production_company_id']) : 0;

    $uploads_dir = 'uploads';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $new_filename = '';
    if (isset($_FILES['image_produit']) && $_FILES['image_produit']['error'] == 0) {
        $filename = $_FILES['image_produit']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image_produit']['tmp_name'], 'image_produit/' . $new_filename);
        } else {
            $_SESSION['erreur'] = "Format de fichier non autorisé";
            header('Location: add.php');
            exit;
        }
    }
    $description = substr($description, 0, 255);

    $sql = 'INSERT INTO `liste` (`produit`, `description`, `prix`, `nombre`, `image_produit`, `Promo`, `actif`, `production_company_id`) 
            VALUES (:produit, :description, :prix, :nombre, :image_produit, :Promo, 1, :production_company_id);';

    $query = $db->prepare($sql);

    $query->bindValue(':produit', $produit, PDO::PARAM_STR);
    $query->bindValue(':description', $description, PDO::PARAM_STR);
    $query->bindValue(':prix', $prix, PDO::PARAM_STR);
    $query->bindValue(':nombre', $nombre, PDO::PARAM_INT);
    $query->bindValue(':image_produit', $new_filename, PDO::PARAM_STR);
    $query->bindValue(':Promo', $promo, PDO::PARAM_INT);
    $query->bindValue(':production_company_id', $production_company_id, PDO::PARAM_INT);

    $query->execute();

    if ($query->rowCount() > 0) {
        $_SESSION['message'] = "Produit ajouté";
    } else {
        $_SESSION['erreur'] = "Erreur lors de l'ajout du produit";
    }

    require_once('close.php');

    header('Location: index.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Ubuntu', sans-serif;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
        }

        label {
            font-size: 1.2rem;
            font-weight: 400;
        }

        .btn {
            font-size: 1rem;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <main class="container">
        <div class="row">
            <section class="col-12">
                <?php
                if (!empty($_SESSION['erreur'])) {
                    echo '<div class="alert alert-danger" role="alert">
                                ' . $_SESSION['erreur'] . '
                            </div>';
                    $_SESSION['erreur'] = "";
                }
                ?>
                <h1>Ajouter un produit</h1>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="produit">Produit</label>
                        <input type="text" id="produit" name="produit" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="prix">Prix</label>
                        <input type="text" id="prix" name="prix" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="number" id="nombre" name="nombre" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="image_produit">Image</label>
                        <input type="file" id="image_produit" name="image_produit" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="Promo">Promo (%)</label>
                        <input type="number" id="Promo" name="Promo" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="production_company_id">Société de production</label>
                        <select class="form-control" id="production_company_id" name="production_company_id">
                            <?php
                            require_once('connect.php');
                            $sql = 'SELECT id, name FROM production_companies';
                            $query = $db->query($sql);
                            $companies = $query->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($companies as $company) {
                                echo '<option value="' . $company['id'] . '">' . htmlspecialchars($company['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <button class="btn btn-primary">Envoyer</button>
                </form>
            </section>
        </div>
    </main>
</body>

</html>