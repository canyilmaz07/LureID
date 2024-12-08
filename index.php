<?php
// index.php
include("public_sources/menu/menu.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID - Keşfet</title>
    <style>
        .discover-container {
            max-width: 95%;
            margin: 80px auto 0;
            padding: 20px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
        }

        .card {
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 3/4;
            background-color: #f0f0f0;
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .card:hover .card-image {
            background-color: #e0e0e0;
        }

        .card-content {
            padding: 12px 8px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #000;
            margin-bottom: 4px;
        }

        .card-description {
            font-size: 12px;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .card-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e0e0e0;
        }

        .card-username {
            font-size: 12px;
            color: #666;
        }

        /* Responsive tasarım için medya sorguları */
        @media (max-width: 1800px) {
            .grid-container {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (max-width: 1400px) {
            .grid-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="discover-container">
        <div class="grid-container">
            <?php for($i = 1; $i <= 18; $i++): ?>
                <div class="card">
                    <div class="card-image"></div>
                    <div class="card-content">
                        <h3 class="card-title">Tasarım Başlığı <?php echo $i; ?></h3>
                        <p class="card-description">
                            Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam, voluptatum.
                        </p>
                        <div class="card-meta">
                            <div class="card-avatar"></div>
                            <span class="card-username">@kullanici<?php echo $i; ?></span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>