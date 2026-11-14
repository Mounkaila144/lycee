{{--
    Pied de page commun des documents PDF : coordonnées de l'établissement.
    Variables : $school (SchoolProfileService::forDocuments()), $extra (texte additionnel optionnel).
--}}
<div style="margin-top:6px;border-top:1px solid #c9d3e3;padding-top:3px;font-size:7.5px;color:#666;text-align:center;line-height:1.3;">
    <strong>{{ $school['display_name'] }}</strong>@if (! empty($school['contact_line'])) — {{ $school['contact_line'] }}@endif
    @if (! empty($extra))
        <br>{{ $extra }}
    @endif
</div>
