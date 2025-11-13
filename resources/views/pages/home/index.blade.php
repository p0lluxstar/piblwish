@extends('layouts.base')

@section('page.title', 'Home')

@section('main-content')
    <div class="container py-4">
        <form method="GET" action="{{ route('home') }}" class="mb-4 d-flex">
            <input type="text" name="url" class="form-control me-2" placeholder="Enter the API URL"
                value="{{ $url ?? '' }}">
            <button type="submit" class="btn btn-primary">Download</button>
        </form>

        @if (empty($url))
            <p class="text-center">Enter the URL and click "Upload" to get the data.</p>
        @elseif(isset($data['error']))
            <div class="alert alert-danger text-center">{{ $data['error'] }}</div>
        @elseif(empty($data))
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Uploading data...</p>
            </div>
        @else
            <ol>
                @foreach ($data as $item)
                    <li><strong>{{ $item['id'] }}</strong></li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
