# Báo cáo cập nhật chức năng bảng lương, thưởng/phạt và phụ cấp

Ngày cập nhật: 25/05/2026

## 1. Tổng quan

Đợt cập nhật này tập trung vào việc chuẩn hóa quy trình tính lương nhân viên, đặc biệt là các khoản phụ cấp, thưởng và phạt.

Trước đây, các khoản thưởng/phạt có thể được nhập trực tiếp khi tính lương, dẫn đến rủi ro mỗi người nhập một kiểu, khó kiểm soát và khó giải trình. Sau cập nhật, hệ thống đã bổ sung danh mục thưởng/phạt dùng chung để quản lý trước các khoản được phép áp dụng.

Khi tính lương, người phụ trách chỉ cần chọn đúng danh mục và nhập số lượt áp dụng. Hệ thống sẽ tự động tính số tiền dựa trên quy định đã cấu hình.

## 2. Các chức năng đã bổ sung

### 2.1. Danh mục thưởng/phạt

Hệ thống bổ sung màn hình quản lý danh mục `Thưởng / Phạt` dành cho admin.

Tại màn hình này, admin có thể:

- Tạo danh mục thưởng.
- Tạo danh mục phạt.
- Cập nhật tên khoản thưởng/phạt.
- Cập nhật số tiền áp dụng cho mỗi lượt.
- Thêm ghi chú/quy định áp dụng.
- Bật hoặc tạm ẩn một danh mục.
- Xóa danh mục không còn sử dụng.

Ví dụ danh mục thưởng:

- Sinh nhật: 100.000đ/lượt.
- Quốc khánh: 200.000đ/lượt.
- Thâm niên: 500.000đ/lượt.

Ví dụ danh mục phạt:

- Đi trễ: 10.000đ/lượt.
- Không check-out: 20.000đ/lượt.
- Vi phạm nội quy: theo mức cấu hình.

### 2.2. Áp dụng thưởng trong bảng lương

Khi tính lương, phần phụ cấp/thưởng không còn nhập tự do tên khoản và số tiền như trước.

Người phụ trách sẽ:

1. Chọn danh mục thưởng đã được cấu hình sẵn.
2. Nhập số lượt áp dụng.
3. Hệ thống tự tính thành tiền.

Công thức:

```text
Thành tiền = Số tiền/lượt x Số lượt
```

Cách này giúp đảm bảo mọi khoản thưởng đều có căn cứ từ danh mục chung, hạn chế việc thưởng tùy ý hoặc nhập sai số tiền.

### 2.3. Áp dụng phạt trong bảng lương

Tương tự phần thưởng, khi áp dụng phạt người phụ trách sẽ:

1. Chọn danh mục phạt đã được cấu hình sẵn.
2. Nhập số lượt áp dụng.
3. Hệ thống tự tính tổng tiền phạt.

Lý do phạt và bằng chứng phạt vẫn được giữ trong màn hình nhập liệu để hỗ trợ giải trình khi cần, nhưng hiện tại không bắt buộc nhập.

Điều này giúp thao tác nhanh hơn trong các trường hợp phạt đơn giản, đồng thời vẫn có chỗ lưu thông tin bổ sung nếu quản lý muốn ghi rõ.

### 2.4. Phụ cấp ăn ca

Hệ thống đã điều chỉnh lại điều kiện áp dụng phụ cấp ăn ca.

Trước đây, chỉ cần có ca làm hợp lệ là nhân viên có thể được cộng phụ cấp ăn ca.

Sau cập nhật:

- Phụ cấp ăn ca chỉ được áp dụng với ca làm từ 7 tiếng trở lên.
- Các ca dưới 7 tiếng sẽ không được cộng phụ cấp ăn ca.
- Mức phụ cấp ăn ca hiện tại vẫn giữ nguyên theo cấu hình hiện có.

Điều chỉnh này giúp việc tính phụ cấp ăn ca sát hơn với quy định vận hành.

## 3. Các thay đổi trên giao diện

### 3.1. Bảng lương nhân viên

Giao diện bảng lương đã được điều chỉnh lại để dễ hiểu hơn:

- Cột `Thưởng` được đổi thành `Phụ cấp`.
- Phần `Khấu trừ` được đổi thành `Phạt`.
- Các khoản thưởng/phạt được hiển thị theo từng dòng rõ ràng.
- Mỗi dòng có các thông tin chính:
  - Danh mục.
  - Số lượt.
  - Thành tiền.
  - Nút xóa.

Các ô nhập liệu trong phần thưởng/phạt cũng được căn chỉnh lại để có chiều cao đồng đều, tránh tình trạng lệch dòng gây khó nhìn.

### 3.2. Màn hình danh mục thưởng/phạt

Màn hình danh mục được chia thành 2 tab:

- `Thưởng`
- `Phạt`

Bảng danh mục được chỉnh để:

- Không tự xuống dòng trong các ô dữ liệu.
- Có thanh cuộn ngang khi màn hình không đủ rộng.
- Nội dung ghi chú dài sẽ được rút gọn bằng dấu `...` để tránh vỡ giao diện.

### 3.3. Màn hình lương của nhân viên

Màn hình lương nhân viên cũng được cập nhật cách hiển thị:

- Hiển thị `Phụ cấp` thay cho `Thưởng`.
- Hiển thị `Tổng phạt` thay cho cách gọi chung chung trước đây.
- Nếu khoản phạt có lý do hoặc bằng chứng, nhân viên có thể xem thông tin này trong chi tiết lương.

## 4. Kiểm soát trạng thái bảng lương

Trong quá trình kiểm tra, phát hiện một số bảng lương đang bị lệch trạng thái:

- Bên ngoài hiển thị là `Bản nháp`.
- Nhưng hệ thống lại hiểu là đã `Quyết toán`.

Điều này khiến khi bấm lưu nháp lại, hệ thống báo lỗi không cho sửa.

Đợt cập nhật này đã xử lý:

- Đồng bộ lại các bảng lương bị lệch trạng thái.
- Điều chỉnh logic kiểm tra để hệ thống dựa vào trạng thái chính xác hơn.
- Bản nháp có thể lưu lại bình thường.
- Bảng lương đã quyết toán vẫn được bảo vệ, không cho chỉnh sửa.

## 5. Lợi ích sau cập nhật

Sau cập nhật, quy trình tính lương có các cải thiện chính:

- Thưởng/phạt được quản lý tập trung theo danh mục.
- Hạn chế nhập thưởng/phạt tùy ý.
- Giảm sai sót khi nhập số tiền.
- Dễ kiểm tra lại căn cứ thưởng/phạt.
- Dễ mở rộng thêm các loại thưởng/phạt mới.
- Giao diện nhập liệu rõ ràng hơn.
- Phụ cấp ăn ca được tính đúng theo điều kiện ca làm từ 7 tiếng.
- Trạng thái bảng lương nháp/quyết toán được xử lý nhất quán hơn.

## 6. Luồng sử dụng đề xuất

### Bước 1: Cấu hình danh mục

Admin vào màn hình `Thưởng / Phạt`, sau đó tạo trước các danh mục cần dùng.

Ví dụ:

- Thưởng sinh nhật.
- Thưởng lễ.
- Thưởng thâm niên.
- Phạt đi trễ.
- Phạt quên check-out.

### Bước 2: Tính lương

Khi tính lương nhân viên, người phụ trách chọn danh mục thưởng/phạt phù hợp và nhập số lượt.

Hệ thống tự động tính thành tiền và cộng/trừ vào bảng lương.

### Bước 3: Lưu nháp hoặc gửi duyệt/quyết toán

Sau khi kiểm tra thông tin:

- Có thể lưu nháp để chỉnh tiếp.
- Có thể gửi duyệt nếu là trưởng ca.
- Admin có thể xác nhận quyết toán.

## 7. Phạm vi đã hoàn tất

Các hạng mục đã hoàn tất trong đợt cập nhật này:

- Thêm màn hình danh mục thưởng/phạt.
- Thêm chức năng tạo/sửa/xóa danh mục thưởng/phạt.
- Thêm chọn danh mục thưởng trong bảng lương.
- Thêm chọn danh mục phạt trong bảng lương.
- Thêm số lượt cho từng khoản thưởng/phạt.
- Tự động tính thành tiền theo danh mục.
- Bỏ bắt buộc lý do và bằng chứng phạt.
- Giữ khả năng nhập lý do/bằng chứng nếu cần.
- Sửa phụ cấp ăn ca chỉ áp dụng cho ca từ 7 tiếng.
- Sửa lỗi bản nháp bị hiểu nhầm là đã quyết toán.
- Cải thiện giao diện bảng lương và bảng danh mục.

## 8. Ghi chú

Các bảng lương đã quyết toán vẫn được khóa để tránh chỉnh sửa sau khi đã chốt.

Các bảng lương còn ở trạng thái nháp vẫn có thể cập nhật lại thưởng/phạt theo danh mục hiện tại.

Nếu sau này cần kiểm soát chặt hơn, hệ thống có thể bổ sung thêm quy định bắt buộc lý do hoặc bằng chứng theo từng loại phạt cụ thể.
