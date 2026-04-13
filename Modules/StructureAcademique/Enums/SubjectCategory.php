<?php

namespace Modules\StructureAcademique\Enums;

enum SubjectCategory: string
{
    case Sciences = 'sciences';
    case Lettres = 'lettres';
    case Langues = 'langues';
    case SciencesHumaines = 'sciences_humaines';
    case EducationPhysique = 'education_physique';
    case Arts = 'arts';
    case Autres = 'autres';
}
