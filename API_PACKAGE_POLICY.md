# Quy chuan goi API bank

## Goi dang ban

- Standard: 20.000 VND/thang, toi da 1 tai khoan ngan hang.
- Plus: 40.000 VND/thang, toi da 2 tai khoan ngan hang.
- Pro: 90.000 VND/thang, toi da 5 tai khoan ngan hang.
- Business: 1.000.000 VND/thang, toi da 200 tai khoan ngan hang, co Website rieng.

Moi goi deu co: Thong tin so du, Lich su giao dich, API, Webhook.

## Thoi han va giam gia

- 1, 2, 3, 6 thang: tinh dung gia theo thang.
- 1 nam: giam 10%.
- 2 nam: giam 20%.
- Mot thang quy uoc 30 ngay de tinh tien con lai va thoi han moi.

## Nang cap goi

- Nang cap la chon goi co han muc tai khoan ngan hang cao hon goi dang con han.
- Nang cap ap dung ngay sau khi thanh toan thanh cong.
- He thong tinh tien chenh lech sau khi tru phan gia tri con lai cua goi cu.
- Han goi moi bat dau lai tu thoi diem mua, khong cong don vao han cu.
- Neu dang co lich ha cap ky sau, lich do bi huy khi nang cap thanh cong.

Cong thuc:

```text
tien_hoan_goi_cu = tien_da_tra_goi_cu * so_giay_con_lai / tong_so_giay_goi_cu
can_thanh_toan = max(0, gia_goi_moi - tien_hoan_goi_cu)
so_du_sau_mua = so_du_hien_tai + tien_hoan_goi_cu - gia_goi_moi
han_moi = thoi_diem_mua + so_thang_moi * 30 ngay
```

## Gia han cung goi

- Gia han cung goi la khach dang dung goi nao va mua them dung goi do.
- Gia han cung goi khong hoan tien phan con lai cua chinh goi do.
- Neu goi hien tai con han: han moi = han hien tai + thoi gian mua them.
- Neu goi hien tai da het han: han moi = thoi diem mua + thoi gian mua them.
- Neu dang co lich ha cap ky sau, lich do bi huy khi khach gia han cung goi thanh cong.

## Ha cap goi

- Ha cap la chon goi co han muc tai khoan ngan hang thap hon goi dang con han.
- Ha cap khong ap dung ngay.
- Ha cap khong hoan tien giua ky.
- Khi khach bam ha cap, he thong chi luu goi ky sau vao cac truong `api_next_*`.
- Goi hien tai tiep tuc chay den het han voi day du quyen loi da mua.
- Khi het han, neu vi du tien, he thong tu tru tien goi ky sau va kich hoat goi do.
- Neu vi khong du tien o thoi diem het han, API tiep tuc bao het han va khach can nap tien/gia han.

## Het han API

- Khi `time_end <= now`, API tra ve `TOKEN_EXPIRED` neu khong co goi ky sau du dieu kien kich hoat.
- Neu co goi ky sau va vi du tien, he thong kich hoat truoc khi tra ket qua API.
- Neu khong du tien, khong kich hoat am vi va khong cap quyen tam.

## Du lieu can luu tren user

Goi hien tai:

- `api_plan`
- `api_account_limit`
- `api_plan_started_at`
- `api_plan_months`
- `api_plan_paid_amount`
- `time_end`

Goi ky sau khi ha cap:

- `api_next_plan`
- `api_next_plan_months`
- `api_next_plan_price`
- `api_next_plan_scheduled_at`

## Nguyen tac bat bien

- Nang cap ap dung ngay va chi thu phan chenh lech sau khi tru gia tri con lai cua goi cu.
- Gia han cung goi se cong them thoi gian vao han hien tai neu con han, khong hoan tien goi dang dung.
- Khong cho ha cap va hoan tien ngay trong ky dang chay.
- Khi doi goi ngay, goi moi bat dau tu ngay mua.
- Khong cho API tiep tuc neu het han ma vi khong du tien gia han.
- Khong duoc lay han muc goi moi de ap dung truoc khi goi moi thuc su kich hoat.
- Moi bien dong tien va doi goi phai ghi `xlogs`.
