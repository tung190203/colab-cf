<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpotCheckItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['group' => 'Vệ sinh', 'title' => 'Mặt quầy bar sạch và khô'],
            ['group' => 'Vệ sinh', 'title' => 'Bồn rửa sạch, không còn ly bẩn'],
            ['group' => 'Vệ sinh', 'title' => 'Sàn nhà sạch, không rác/vết ướt'],
            ['group' => 'Vệ sinh', 'title' => 'Bàn khách đã được lau sạch'],
            ['group' => 'Không gian', 'title' => 'Bàn ghế sắp xếp ngay ngắn đúng sơ đồ'],
            ['group' => 'Không gian', 'title' => 'Thùng rác có lót nilon, không đầy quá 2/3'],
            ['group' => 'Setup', 'title' => 'Ống hút, thìa, khăn giấy setup đầy đủ'],
            ['group' => 'Setup', 'title' => 'Tủ lạnh/kệ NVL gọn tự nhiên, có nhãn ngày'],
            ['group' => 'Tác phong', 'title' => 'Nhân viên mặc đúng đồng phục, tạp dề sạch'],
            ['group' => 'Tác phong', 'title' => 'Nhân viên không dùng điện thoại cá nhân'],
            ['group' => 'Tác phong', 'title' => 'Không tụ tập nói chuyện riêng'],
            ['group' => 'Phục vụ', 'title' => 'Khách trong quán được quan tâm'],
        ];

        foreach ($items as $index => $item) {
            \App\Models\SpotCheckItem::updateOrCreate(
                ['title' => $item['title']],
                [
                    'group' => $item['group'],
                    'order' => $index + 1,
                    'active' => true,
                ]
            );
        }
    }
}
