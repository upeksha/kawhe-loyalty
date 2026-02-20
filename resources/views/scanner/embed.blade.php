@extends('layouts.scanner-embed')

@section('content')
    @include('scanner._content')
@endsection

@push('scripts')
    @include('scanner._scripts')
@endpush
