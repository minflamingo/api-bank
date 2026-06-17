@extends('layouts/contentNavbarLayout')

@section('title', 'Lịch sử giao dịch Vietcombank')

{{-- Nếu cần CSS/JS vendor riêng, bạn thêm ở @section('vendor-style') / @section('vendor-script') --}}

@section('content')
<div data-bank-history-page>
  <div data-bank-history-content>
<!-- [ Main Content ] start -->
<section class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Lịch sử giao dịch Vietcombank</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ url('/') }}">
                                    <i class="feather icon-home"></i>
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ url('/') }}">Trang chủ</a>
                            </li>
                            <li class="breadcrumb-item">
                                Lịch sử giao dịch
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        
        <!-- [ Main Content ] start -->
        @include('payment._history_filters')

        <div class="row">
            <!-- [ stiped-table ] start -->
            <div class="col-xl-12">
                <div class="card">
                    <!-- HEADER -->
                    <div class="card-header">
                        <div class="row">
                            <div class="col-12">
                                <h5>Lịch sử giao dịch của: <span class="text-primary">{{ $acc->account }}</span></h5>
                            </div>
                        </div>
                    </div>

                    <!-- BODY -->
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table w-100" id="datatable">
                                <thead>
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Loại GD</th>
                                        <th>Mã GD</th>
                                        <th>Số tiền</th>
                                        <th>Người gửi/nhận</th>
                                        <th>Nội dung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @if(!empty($transactions))
                                    @foreach($transactions as $item)
                                        <tr>
                                            <td> @php
                    if (!empty($item['PostingDate']) && !empty($item['PostingTime'])) {
                        $dateTime = \Carbon\Carbon::createFromFormat('Y-m-d His', $item['PostingDate'] . ' ' . $item['PostingTime']);
                        echo $dateTime->format('d/m/Y H:i:s');
                    }
                @endphp</td>
                                            <td>
                                                @if(($item['CD'] ?? '') === '+')
                                                    <span class="text-success">Nhận tiền</span>
                                                @else
                                                    <span class="text-danger">Trừ tiền</span>
                                                @endif
                                            </td>
                                            <td style="color:blue">{{ $item['Reference'] ?? '' }}</td>
                                            <td style="color:green">
                                                {{-- Vừa bỏ dấu phẩy, vừa format --}}
                                                {{ number_format(str_replace(',', '', $item['Amount'] ?? '0')) }}đ
                                            </td>
                                            <td>@include('payment._history_party_cell', ['party' => $item['_party_info'] ?? []])</td>
                                            <td>{{ $item['Description'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6">Không có dữ liệu giao dịch</td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- END BODY -->
                </div>
            </div>
            <!-- [ stiped-table ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</section>
<!-- [ Main Content ] end -->
  </div>
</div>

@include('payment._history_scripts')
@endsection

@section('page-script')

@endsection
