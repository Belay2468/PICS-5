<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageClass = 'dashboard-page';
$pageTitle = 'Home';
require __DIR__ . '/../includes/user_header.php';

$categories = $conn->query("
    SELECT
        c.*,
        COUNT(i.id) AS item_count
    FROM categories c
    LEFT JOIN inventory_items i
        ON i.category_id = c.id
    GROUP BY c.id
    ORDER BY c.id
");



?>

<div class="dashboard-container">

    <!-- ==========================================
        HERO SECTION
    =========================================== -->
    <section class="user-hero">

        <h1>
            <span>Welcome to the</span> <br>  
            PhilHealth Inventory Control System
        </h1>

        <div class="hero-divider"></div>

        <form
            class="user-search"
            action="search.php"
            method="GET"
        >

            <i class="fa-solid fa-magnifying-glass search-icon"></i>

            <input
                type="text"
                name="q"
                placeholder="Search supplies, items, or categories..."
                autocomplete="off"
            >

            <button type="submit">

                <i class="fa-solid fa-magnifying-glass"></i>

            </button>

        </form>

    </section>

    <!-- ==========================================
        CATEGORY SECTION
    =========================================== -->

    <section class="category-section">

        <div class="category-grid">

            <?php while($cat = $categories->fetch_assoc()): ?>

              <?php
                $class = match((int)$cat['id']) {
                    1 => 'it-card',
                    2 => 'medical-card',
                    3 => 'office-card',
                    4 => 'other-card',
                    default => ''
                };
              ?>

                <a
                    href="category.php?id=<?= $cat['id'] ?>"
                    class="category-card <?= $class ?>"
                >

                    <div class="category-icon">

                        <?php
                        if(str_contains($cat['icon'],'fa-')){
                            echo '<i class="'.h($cat['icon']).'"></i>';
                        }else{
                            echo h($cat['icon']);
                        }
                        ?>

                    </div>

                    <div class="category-info">

                        <h2>
                            <?= h($cat['name']) ?>
                        </h2>

                        <p>
                            <?= h($cat['description']) ?>
                        </p>

                        <span class="category-count">

                            <?= (int)$cat['item_count'] ?>

                            <?= (int)$cat['item_count'] == 1 ? 'Item' : 'Items' ?>

                        </span>

                    </div>

                    <div class="category-arrow">

                        <i class="fa-solid fa-arrow-right"></i>

                    </div>

                </a>

            <?php endwhile; ?>

        </div>

    </section>

</div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>