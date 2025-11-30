<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kategori - Renewa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .category-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .category-gradient {
            background: linear-gradient(135deg, #f0fdf4 0%, #e0f2f1 100%);
        }
    </style>
</head>
<body class="bg-gray-50 category-gradient">
    @include('layouts.partials.navbar')

    <main class="min-h-screen flex items-center justify-center pt-24 pb-12">
        <div class="container mx-auto px-4 text-center">
            <header class="mb-12">
                <h1 class="text-4xl font-bold text-gray-800">Satu Langkah Menuju Energi Hijau</h1>
                <p class="text-lg text-gray-600 mt-2">Pilih Kategori Pembelian yang Sesuai dengan Kebutuhan dan Keinginanmu!</p>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Card Retail -->
                <a href="{{ route('buyer.marketplace', ['category' => 'Retail', 'min_purchase' => 10]) }}" class="category-card block bg-white p-8 rounded-2xl shadow-lg">
                    <div class="w-20 h-20 mx-auto bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                        <img src="https://img.icons8.com/plasticine/100/shopping-cart.png" alt="Retail" class="w-12 h-12"/>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Retail</h3>
                    <p class="text-gray-600 mb-4">Layanan kepada pelanggan untuk kebutuhan retail.</p>
                    <span class="font-semibold text-green-600">Min. pembelian 10 unit REC</span>
                </a>

                <!-- Card Signature -->
                <a href="{{ route('buyer.marketplace', ['category' => 'Signature', 'min_purchase' => 0]) }}" class="category-card block bg-white p-8 rounded-2xl shadow-lg">
                    <div class="w-20 h-20 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-6">
                        <img src="https://img.icons8.com/plasticine/100/conference-call.png" alt="Signature" class="w-12 h-12"/>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Signature</h3>
                    <p class="text-gray-600 mb-4">Energi hijau untuk keperluan sehari-hari.</p>
                    <span class="font-semibold text-green-600">Pembelian REC &lt; 10 unit</span>
                </a>

                <!-- Card Enterprise -->
                <a href="{{ route('buyer.marketplace', ['category' => 'Enterprise', 'min_purchase' => 200]) }}" class="category-card block bg-white p-8 rounded-2xl shadow-lg">
                    <div class="w-20 h-20 mx-auto bg-purple-100 rounded-full flex items-center justify-center mb-6">
                        <img src="https://img.icons8.com/plasticine/100/organization.png" alt="Enterprise" class="w-12 h-12"/>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Enterprise</h3>
                    <p class="text-gray-600 mb-4">Bisnis skala kecil, menengah, hingga besar.</p>
                    <span class="font-semibold text-green-600">Min. pembelian 200 unit REC</span>
                </a>
            </div>
        </div>
    </main>
</body>
</html>