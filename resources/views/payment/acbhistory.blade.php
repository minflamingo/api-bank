@extends('layouts/contentNavbarLayout')

@section('title', 'Lịch sử giao dịch ACB')

@section('content')
<div data-bank-history-page>
  <div data-bank-history-content>
<section class="pcoded-main-container">
  <div class="pcoded-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Lịch sử giao dịch ACB</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item">
                <a href="{{ url('/') }}"><i class="feather icon-home"></i></a>
              </li>
              <li class="breadcrumb-item">ACB</li>
              <li class="breadcrumb-item">Lịch sử giao dịch</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <!-- Main Content -->
    @include('payment._history_filters')

    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <div class="card-header">
            <h5>
              Lịch sử giao dịch của: 
              <span class="text-primary">{{ $acc->stk }}</span>
            </h5>
          </div>
          <div class="card-body table-border-style">
            <div class="table-responsive">
              <table class="table" id="datatable">
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
                        {{-- Hiển thị thời gian theo activeDatetime (hoặc postingDate/effectiveDate tuỳ nhu cầu) --}}
                        <td>
                          @if(!empty($item['activeDatetime']))
                            {{ date('d/m/Y H:i:s', $item['activeDatetime'] / 1000) }}
                          @endif
                        </td>
						<td>
							@if(($item['type'] ?? '') === 'IN')
								<span class="text-success">Nhận tiền</span>
							@else
								<span class="text-danger">Trừ tiền</span>
							 @endif			
						</td>												
                        <td style="color:blue">{{ $item['transactionNumber'] ?? '' }}</td>
                        <td style="color:green">{{ number_format($item['amount'] ?? 0) }}đ</td>
                        <td>@include('payment._history_party_cell', ['party' => $item['_party_info'] ?? []])</td>
                        <td>{{ $item['description'] ?? '' }}</td>
                      </tr>
                    @endforeach
                  @else
                    <tr>
                      <td colspan="6">Không có dữ liệu</td>
                    </tr>
                  @endif
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
  </div>
</div>

@include('payment._history_scripts')
@endsection
