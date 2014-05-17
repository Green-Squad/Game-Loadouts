@extends('layout')

@section('subtitle')
Submissions
@stop

@section('css')
<link href="/css/color/green.css" rel="stylesheet" />
<link href="{{ asset('font-awesome/css/font-awesome.min.css') }}" rel="stylesheet" type="text/css">
@stop

@section('content')
@if(HelperController::adsEnabled())
<div class="col-md-8">
    @else
    <div class="col-md-12">
        @endif
        <h2>Submissions</h2>
        <span class="line"> <span class="sub-line"></span></span>
        <table class="table table-striped tablesorter">
            <thead>
                <tr>
                    <th>Game <i class="fa fa-sort"></i></th>
                    <th>Weapon <i class="fa fa-sort"></i></th>
                    <th>Attachments <i class="fa fa-sort"></i></th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loadouts as $loadout)
                <?php
                $weapon = Weapon::findOrFail($loadout -> weapon_id);
                ?>
                <tr id="loadout-{{ $loadout -> id }}">
                    <td>{{ $weapon -> game_id }}</td>
                    <td>{{ $weapon -> name }}</td>
                    <td>
                    <ul>
                        @foreach(Loadout::findOrFail($loadout -> id) -> attachments as $attachment)
                        <li>
                            {{ $attachment -> name }}
                        </li>
                        @endforeach
                    </ul></td>
                    <td><a class="clickable btn btn-danger" href="javascript:void(0)" data-game_id="{{ $weapon -> game_id }}" data-weapon_name="{{ $weapon -> name }}" data-loadout_id="{{ $loadout -> id }}"> <span class="glyphicon glyphicon-trash"></span></a></td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if(HelperController::adsEnabled())
    <div class="col-md-4">
        <h2>Advertisement</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        <style>
            .game-loadouts-responsive-sidebar {
                width: 300px;
                height: 250px;
            }
            @media (min-width: 800px) {
                .game-loadouts-responsive-sidebar {
                    width: 300px;
                    height: 600px;
                }
            }
        </style>
        <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
        <!-- Game Loadouts Responsive Sidebar -->
        <ins class="adsbygoogle game-loadouts-responsive-sidebar"
        style="display:inline-block"
        data-ad-client="ca-pub-9067954073014278"
        data-ad-slot="2846988777"></ins>
        <script>
            ( adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>
    @endif
    @stop

    @section('scripts')
    <script type="text/javascript">
        $('.clickable').click(function() {
            var game_id = $(this).data('game_id');
            ;
            var weapon_name = $(this).data('weapon_name');
            ;
            var loadout_id = $(this).data('loadout_id');
            $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/detach', function(data) {
                $('#loadout-' + loadout_id).fadeOut();
            }, 'json');

        });
    </script>
    <script src="{{ asset('js/tablesorter/jquery.tablesorter.js') }}"></script>
    <script src="{{ asset('js/tablesorter/tables.js') }}"></script>
    
    @stop