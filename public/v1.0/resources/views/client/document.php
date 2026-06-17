<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$title = 'Tài liệu API | ' . $NNL->site('title');
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
';
$body['footer'] = '
    
';
require_once __DIR__ . '/../../../core/is_user.php';
CheckLogin();
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/nav.php';
?>

<!-- [ Main Content ] start -->
<section class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Tài liệu API</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Tài liệu API</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ stiped-table ] start -->
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">URL API</h5>
                        <div class="input-group mb-3">
                            <input type="text" id="url" class="form-control" value="<?= BASE_URL('api/historymomo/token') ?>"
                                readonly>
                            <div class="input-group-append">
                                <button class="copy btn btn-primary mr-2" onclick="copy()"
                                    data-clipboard-target="#url">COPY</button>
                                <a href="<?= BASE_URL('client/document') ?>" class="btn btn-dark">Tài Liệu</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-responsive" id="tblRequest">
                            <thead>
                                <tr>
                                    <th>
                                        Tham số
                                    </th>
                                    <th>
                                        Dữ liệu
                                    </th>
                                    <th>
                                        Ví dụ
                                    </th>
                                    <th>
                                        Chú thích
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b class="text-danger">token</b></td>
                                    <td>string</td>
                                    <td>39D6670A-1B9A-A12B-ADB0-DB020B35F5CF</td>
                                    <td>Token của tài khoản momo cần POST</td>
                                </tr>
                            </tbody>
                        </table> <br />
                        <div class="bg-light p-2 text-danger">
                            Response
                            <pre><code class="php">
{
    "status": true,
    "message": "Thành công",
    "momoMsg": {
        "tranList": [
            {
                "tranId": 23643551872,
                "id": "1651314554074_01657385033",
                "partnerId": "0888567890",
                "partnerName": "ĐINH VIỆT CƯỜNG",
                "comment": "6575",
                "amount": 640,
                "millisecond": 1651314554074
            },
            {
                "tranId": 23637631827,
                "id": "1651297613132_01657385033",
                "partnerId": "01677890408",
                "partnerName": "HOÀNG NGỌC NHƯ",
                "comment": "5874",
                "amount": 4400,
                "millisecond": 1651297613132
            }
        ]
    }
}
                            </code></pre>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">URL API VIETCOMBANK</h5>
                        <div class="input-group mb-3">
                            <input type="text" id="urlvcb" class="form-control"
                                value="<?= BASE_URL('api/historyvietcombank/token') ?>" readonly>
                            <div class="input-group-append">
                                <button class="copy btn btn-primary mr-2" onclick="copy()"
                                    data-clipboard-target="#urlvcb">COPY</button>
                                <a href="<?= BASE_URL('API.zip') ?>" class="btn btn-dark">Tài Liệu</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-responsive" id="tblRequest">
                            <thead>
                                <tr>
                                    <th>
                                        Tham số
                                    </th>
                                    <th>
                                        Dữ liệu
                                    </th>
                                    <th>
                                        Ví dụ
                                    </th>
                                    <th>
                                        Chú thích
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b class="text-danger">token</b></td>
                                    <td>string</td>
                                    <td>39D6670A-1B9A-A12B-ADB0-DB020B35F5CF</td>
                                    <td>Token của tài khoản vietcombank cần POST</td>
                                </tr>
                            </tbody>
                        </table> <br />
                        <div class="bg-light p-2 text-danger">
                            Response
                            <pre><code class="php">
{
    "mid": "14",
    "code": "00",
    "des": "success",
    "transactions": [
        {
            "tranDate": "03/01/2024",
            "TransactionDate": "03/01/2024",
            "Reference": "5219 - 11083",
            "CD": "+",
            "Amount": "10,000",
            "Description": "304531.130223.121329.NICKTACO napcoin1",
            "PCTime": "121330",
            "DorCCode": "C",
            "EffDate": "2023-02-13",
            "PostingDate": "2023-02-13",
            "PostingTime": "121330",
            "Remark": "304531.130223.121329.NICKTACO napcoin1",
            "SeqNo": "11083",
            "TnxCode": "34",
            "Teller": "5219"
        }
    ]
}
                            </code></pre>
                        </div>
                    </div>
                </div>

            </div>
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">URL API MBBANK</h5>
                        <div class="input-group mb-3">
                            <input type="text" id="urlmbbank" class="form-control"
                                value="<?= BASE_URL('api/historymbbank/token') ?>" readonly>
                            <div class="input-group-append">
                                <button class="copy btn btn-primary mr-2" onclick="copy()"
                                    data-clipboard-target="#urlmbbank">COPY</button>
                                <a href="<?= BASE_URL('API.zip') ?>" class="btn btn-dark">Tài Liệu</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-responsive" id="tblRequest">
                            <thead>
                                <tr>
                                    <th>
                                        Tham số
                                    </th>
                                    <th>
                                        Dữ liệu
                                    </th>
                                    <th>
                                        Ví dụ
                                    </th>
                                    <th>
                                        Chú thích
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b class="text-danger">token</b></td>
                                    <td>string</td>
                                    <td>39D6670A-1B9A-A12B-ADB0-DB020B35F5CF</td>
                                    <td>Token của tài khoản mbbank cần POST</td>
                                </tr>
                            </tbody>
                        </table> <br />
                        <div class="bg-light p-2 text-danger">
                            Response
                            <pre><code class="php">
{
    "status": "success",
    "message": "Thành công",
    "TranList": [
        {
            "tranId": "FT23219370350054\\E17",
            "postingDate": "07/08/2023 00:01:00",
            "transactionDate": "06/08/2023 22:55:00",
            "accountNo": "990919072000",
            "creditAmount": "30000",
            "debitAmount": "0",
            "currency": "VND",
            "description": "CUSTOMER cms2471. TU: DAO VAN LINH",
            "availableBalance": null,
            "beneficiaryAccount": null
        },
        {
            "tranId": "FT23217019574662\\BNK",
            "postingDate": "05/08/2023 14:54:00",
            "transactionDate": "05/08/2023 14:54:00",
            "accountNo": "990919072000",
            "creditAmount": "30000",
            "debitAmount": "0",
            "currency": "VND",
            "description": "CUSTOMER QR   cms2811   Ma giao dich  Trace2 23979 Trace 223979",
            "availableBalance": null,
            "beneficiaryAccount": null
        },
        {
            "tranId": "FT23217060767427\\BNK",
            "postingDate": "05/08/2023 12:58:00",
            "transactionDate": "05/08/2023 12:58:00",
            "accountNo": "990919072000",
            "creditAmount": "30000",
            "debitAmount": "0",
            "currency": "VND",
            "description": "CUSTOMER MBVCB 3973808215 091310 cms2110 CT  tu 1028510335 TRAN MINH DUC toi 990 919072000 DINH VIET CUONG Ngan hang  Quan Doi  MB    Ma giao dich  Trac",
            "availableBalance": null,
            "beneficiaryAccount": null
        }
    ]
}
                            </code></pre>
                        </div>
                    </div>
                </div>

            </div>
            <!-- [ stiped-table ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</section>
<script>
new ClipboardJS(".copy");

function copy() {
    cuteToast({
        type: "success",
        message: "Đã sao chép vào bộ nhớ tạm",
        timer: 5000
    });
}
</script>
<?php require_once(__DIR__ . '/footer.php'); ?>