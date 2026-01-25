@extends('layout')

@section('title', 'Resume Editor')

@section('main')
<div x-data="resumeEditor()"
     x-init="mailConfigured = {{ $mailConfigured ? 'true' : 'false' }}; notificationRecipientCount = {{ $notificationRecipientCount }};"
     class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>
            <h1 class="text-3xl font-heading font-bold text-primary mt-2">Resume Editor</h1>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.resume.preview') }}"
               class="px-4 py-2 text-primary border border-primary rounded-md hover:bg-primary/5 transition-colors">
                <i class="fa-solid fa-eye mr-2"></i>Preview
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Error Display --}}
    <template x-if="errors.length > 0">
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <ul class="list-disc list-inside">
                <template x-for="error in errors" :key="error">
                    <li x-text="error"></li>
                </template>
            </ul>
        </div>
    </template>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            <template x-for="tab in tabs" :key="tab.id">
                <button type="button"
                        @click="activeTab = tab.id"
                        :class="activeTab === tab.id
                            ? 'border-primary text-primary bg-white'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
                    <i :class="tab.icon" class="mr-2"></i>
                    <span x-text="tab.label"></span>
                </button>
            </template>
        </nav>
    </div>

    <form @submit.prevent="save" class="pb-24">
        {{-- Version Tab --}}
        <div x-show="activeTab === 'version'" x-cloak>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Version Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version Number</label>
                        <input type="text"
                               x-model="version"
                               pattern="\d{4}\.\d+\.\d+"
                               placeholder="2026.1.0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                        <p class="mt-1 text-sm text-gray-500">Format: YYYY.MAJOR.MINOR (e.g., 2026.1.0)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">DOCX Status</label>
                        <div class="mt-2">
                            @if($docxExists)
                                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fa-solid fa-check mr-1"></i> DOCX exists for current version
                                </span>
                            @else
                                <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fa-solid fa-exclamation-triangle mr-1"></i> No DOCX for current version
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(count($availableVersions) > 0)
                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Available Versions</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($availableVersions as $v)
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                    {{ $v['version'] }} ({{ date('M j, Y', $v['created']) }})
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Personal Tab --}}
        <div x-show="activeTab === 'personal'" x-cloak>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Personal Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" x-model="data.personal.name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Professional Title *</label>
                        <input type="text" x-model="data.personal.title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" x-model="data.personal.email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" x-model="data.personal.phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">LinkedIn URL</label>
                        <input type="text" x-model="data.personal.linkedin"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Professional Summary</label>
                        <textarea x-model="data.personal.summary" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Skills Tab --}}
        <div x-show="activeTab === 'skills'" x-cloak>
            <div class="space-y-6">
                {{-- Top Skills --}}
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Top Skills</h2>
                        <button type="button" @click="addSkillCategory('top')"
                                class="text-sm text-primary hover:underline">
                            <i class="fa-solid fa-plus mr-1"></i> Add Category
                        </button>
                    </div>
                    <template x-for="(category, catIdx) in data.skills.top" :key="catIdx">
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4 mb-3">
                                <input type="text" x-model="category.title" placeholder="Category Title"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <button type="button" @click="removeSkillCategory('top', catIdx)"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(skill, skillIdx) in category.list" :key="skillIdx">
                                    <div class="flex items-center gap-2">
                                        <input type="text" x-model="category.list[skillIdx]" placeholder="Skill"
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                        <button type="button" @click="category.list.splice(skillIdx, 1)"
                                                class="text-red-600 hover:text-red-800 px-2">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="category.list.push('')"
                                        class="text-sm text-primary hover:underline">
                                    <i class="fa-solid fa-plus mr-1"></i> Add Skill
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Other Skills --}}
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Other Skills</h2>
                        <button type="button" @click="addSkillCategory('other')"
                                class="text-sm text-primary hover:underline">
                            <i class="fa-solid fa-plus mr-1"></i> Add Category
                        </button>
                    </div>
                    <template x-for="(category, catIdx) in data.skills.other" :key="catIdx">
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4 mb-3">
                                <input type="text" x-model="category.title" placeholder="Category Title"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <button type="button" @click="removeSkillCategory('other', catIdx)"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(skill, skillIdx) in category.list" :key="skillIdx">
                                    <div class="flex items-center gap-2">
                                        <input type="text" x-model="category.list[skillIdx]" placeholder="Skill"
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                        <button type="button" @click="category.list.splice(skillIdx, 1)"
                                                class="text-red-600 hover:text-red-800 px-2">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="category.list.push('')"
                                        class="text-sm text-primary hover:underline">
                                    <i class="fa-solid fa-plus mr-1"></i> Add Skill
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Technical Profile Tab --}}
        <div x-show="activeTab === 'profile'" x-cloak>
            <div class="space-y-6">
                {{-- Main Profile --}}
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Main Profile</h2>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                        <input type="text" x-model="data.technicalProfile.main.category"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Skills</label>
                        <div class="space-y-2">
                            <template x-for="(skill, idx) in data.technicalProfile.main.skills" :key="idx">
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="skill.skill" placeholder="Skill name"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                    <button type="button" @click="data.technicalProfile.main.skills.splice(idx, 1)"
                                            class="text-red-600 hover:text-red-800 px-2">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="data.technicalProfile.main.skills.push({ skill: '' })"
                                    class="text-sm text-primary hover:underline">
                                <i class="fa-solid fa-plus mr-1"></i> Add Skill
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Secondary Profile --}}
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Secondary Profile</h2>
                        <button type="button" @click="addSecondaryCategory()"
                                class="text-sm text-primary hover:underline">
                            <i class="fa-solid fa-plus mr-1"></i> Add Category
                        </button>
                    </div>
                    <template x-for="(cat, catIdx) in data.technicalProfile.secondary" :key="catIdx">
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4 mb-3">
                                <input type="text" x-model="cat.category" placeholder="Category Name"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <button type="button" @click="data.technicalProfile.secondary.splice(catIdx, 1)"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <div class="space-y-3">
                                <template x-for="(skill, skillIdx) in cat.skills" :key="skillIdx">
                                    <div class="flex items-start gap-2 p-3 bg-white rounded border">
                                        <div class="flex-1 grid gap-2 md:grid-cols-3">
                                            <input type="text" x-model="skill.skill" placeholder="Skill"
                                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                            <input type="number" x-model.number="skill.years" placeholder="Years" min="0"
                                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                            <input type="text" x-model="skill.description" placeholder="Description"
                                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                        </div>
                                        <button type="button" @click="cat.skills.splice(skillIdx, 1)"
                                                class="text-red-600 hover:text-red-800 px-2 mt-2">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="cat.skills.push({ skill: '', years: null, description: '' })"
                                        class="text-sm text-primary hover:underline">
                                    <i class="fa-solid fa-plus mr-1"></i> Add Skill
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Experience Tab --}}
        <div x-show="activeTab === 'experience'" x-cloak>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Work Experience</h2>
                    <button type="button" @click="addExperience()"
                            class="text-sm text-primary hover:underline">
                        <i class="fa-solid fa-plus mr-1"></i> Add Job
                    </button>
                </div>
                <template x-for="(job, jobIdx) in data.experience" :key="jobIdx">
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="moveItem(data.experience, jobIdx, -1)"
                                        :disabled="jobIdx === 0"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" @click="moveItem(data.experience, jobIdx, 1)"
                                        :disabled="jobIdx === data.experience.length - 1"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                            </div>
                            <button type="button" @click="data.experience.splice(jobIdx, 1)"
                                    class="text-red-600 hover:text-red-800">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Job Title *</label>
                                <input type="text" x-model="job.jobTitle" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company *</label>
                                <input type="text" x-model="job.company" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <input type="text" x-model="job.location"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label>
                                    <input type="text" x-model="job.dates[0]" placeholder="2020"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Year</label>
                                    <input type="text" x-model="job.dates[1]" placeholder="Present"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bullet Points</label>
                            <div class="space-y-2">
                                <template x-for="(bullet, bulletIdx) in job.bullets" :key="bulletIdx">
                                    <div class="flex items-start gap-2">
                                        <textarea x-model="job.bullets[bulletIdx]" rows="2"
                                                  class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50"></textarea>
                                        <button type="button" @click="job.bullets.splice(bulletIdx, 1)"
                                                class="text-red-600 hover:text-red-800 px-2 mt-2">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="job.bullets.push('')"
                                        class="text-sm text-primary hover:underline">
                                    <i class="fa-solid fa-plus mr-1"></i> Add Bullet
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Education Tab --}}
        <div x-show="activeTab === 'education'" x-cloak>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Education</h2>
                    <button type="button" @click="addEducation()"
                            class="text-sm text-primary hover:underline">
                        <i class="fa-solid fa-plus mr-1"></i> Add Education
                    </button>
                </div>
                <template x-for="(edu, eduIdx) in data.education" :key="eduIdx">
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="moveItem(data.education, eduIdx, -1)"
                                        :disabled="eduIdx === 0"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" @click="moveItem(data.education, eduIdx, 1)"
                                        :disabled="eduIdx === data.education.length - 1"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                            </div>
                            <button type="button" @click="data.education.splice(eduIdx, 1)"
                                    class="text-red-600 hover:text-red-800">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Institution *</label>
                                <input type="text" x-model="edu.institution" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Degree</label>
                                <input type="text" x-model="edu.degree"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label>
                                    <input type="text" x-model="edu.dates[0]" placeholder="2016"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Year</label>
                                    <input type="text" x-model="edu.dates[1]" placeholder="2020"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="edu.description" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50"></textarea>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Projects Tab --}}
        <div x-show="activeTab === 'projects'" x-cloak>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Selected Projects</h2>
                    <button type="button" @click="addProject()"
                            class="text-sm text-primary hover:underline">
                        <i class="fa-solid fa-plus mr-1"></i> Add Project
                    </button>
                </div>
                <template x-for="(project, projIdx) in data.projects" :key="projIdx">
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="moveItem(data.projects, projIdx, -1)"
                                        :disabled="projIdx === 0"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" @click="moveItem(data.projects, projIdx, 1)"
                                        :disabled="projIdx === data.projects.length - 1"
                                        class="text-gray-500 hover:text-gray-700 disabled:opacity-30">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                            </div>
                            <button type="button" @click="data.projects.splice(projIdx, 1)"
                                    class="text-red-600 hover:text-red-800">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="grid gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Project Name *</label>
                                <input type="text" x-model="project.projectName" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="project.description" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bullet Points</label>
                                <div class="space-y-2">
                                    <template x-for="(bullet, bulletIdx) in project.bullets" :key="bulletIdx">
                                        <div class="flex items-start gap-2">
                                            <textarea x-model="project.bullets[bulletIdx]" rows="2"
                                                      class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50"></textarea>
                                            <button type="button" @click="project.bullets.splice(bulletIdx, 1)"
                                                    class="text-red-600 hover:text-red-800 px-2 mt-2">
                                                <i class="fa-solid fa-times"></i>
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" @click="project.bullets.push('')"
                                            class="text-sm text-primary hover:underline">
                                        <i class="fa-solid fa-plus mr-1"></i> Add Bullet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- FAB Save Button with Options --}}
        <div class="fixed bottom-8 right-8 flex flex-col gap-3 items-end" x-data="{ showOptions: false }">
            <div x-show="showOptions" x-cloak class="bg-white rounded-lg shadow-lg border border-gray-200 p-4 mb-2 w-64">
                <label class="flex items-start gap-3 mb-3">
                    <input type="checkbox"
                           name="notify_recipients"
                           x-model="notifyRecipients"
                           :disabled="!mailConfigured"
                           class="w-4 h-4 rounded cursor-pointer mt-1">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Send email notification to share code recipients</span>
                        <template x-if="!mailConfigured">
                            <p class="text-xs text-gray-500 mt-1">(mail not configured)</p>
                        </template>
                        <template x-if="mailConfigured && notificationRecipientCount > 0">
                            <p class="text-xs text-blue-600 mt-1">Will notify {{ $notificationRecipientCount }} recipient(s)</p>
                        </template>
                    </div>
                </label>
            </div>

            <button type="button"
                    @click="showOptions = !showOptions"
                    class="px-4 py-2 bg-gray-200 text-gray-800 font-medium rounded-full hover:bg-gray-300 transition-colors flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i>
                Options
            </button>

            <button type="submit"
                    :disabled="saving"
                    class="px-6 py-3 bg-primary text-white font-medium rounded-full shadow-lg hover:bg-primary/90 transition-all disabled:opacity-50 flex items-center gap-2">
                <i class="fa-solid" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
        </div>
    </form>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function resumeEditor() {
    return {
        activeTab: 'version',
        saving: false,
        errors: [],
        notifyRecipients: false,
        mailConfigured: false,
        notificationRecipientCount: 0,
        version: @json($version),
        data: @json($data),
        tabs: [
            { id: 'version', label: 'Version', icon: 'fa-solid fa-code-branch' },
            { id: 'personal', label: 'Personal', icon: 'fa-solid fa-user' },
            { id: 'skills', label: 'Skills', icon: 'fa-solid fa-star' },
            { id: 'profile', label: 'Technical Profile', icon: 'fa-solid fa-microchip' },
            { id: 'experience', label: 'Experience', icon: 'fa-solid fa-briefcase' },
            { id: 'education', label: 'Education', icon: 'fa-solid fa-graduation-cap' },
            { id: 'projects', label: 'Projects', icon: 'fa-solid fa-diagram-project' },
        ],

        init() {
            // Ensure arrays exist
            if (!this.data.skills) this.data.skills = { top: [], other: [] };
            if (!this.data.skills.top) this.data.skills.top = [];
            if (!this.data.skills.other) this.data.skills.other = [];
            if (!this.data.technicalProfile) this.data.technicalProfile = { main: { category: '', skills: [] }, secondary: [] };
            if (!this.data.technicalProfile.main) this.data.technicalProfile.main = { category: '', skills: [] };
            if (!this.data.technicalProfile.secondary) this.data.technicalProfile.secondary = [];
            if (!this.data.experience) this.data.experience = [];
            if (!this.data.education) this.data.education = [];
            if (!this.data.projects) this.data.projects = [];

            // Ensure dates arrays exist for experience and education
            this.data.experience.forEach(job => {
                if (!job.dates) job.dates = ['', ''];
                if (!job.bullets) job.bullets = [];
            });
            this.data.education.forEach(edu => {
                if (!edu.dates) edu.dates = ['', ''];
            });
            this.data.projects.forEach(proj => {
                if (!proj.bullets) proj.bullets = [];
            });
        },

        addSkillCategory(type) {
            this.data.skills[type].push({ title: '', list: [''] });
        },

        removeSkillCategory(type, index) {
            this.data.skills[type].splice(index, 1);
        },

        addSecondaryCategory() {
            this.data.technicalProfile.secondary.push({
                category: '',
                skills: [{ skill: '', years: null, description: '' }]
            });
        },

        addExperience() {
            this.data.experience.push({
                jobTitle: '',
                company: '',
                location: '',
                dates: ['', ''],
                bullets: ['']
            });
        },

        addEducation() {
            this.data.education.push({
                institution: '',
                degree: '',
                dates: ['', ''],
                description: ''
            });
        },

        addProject() {
            this.data.projects.push({
                projectName: '',
                description: '',
                bullets: ['']
            });
        },

        moveItem(array, index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= array.length) return;
            [array[index], array[newIndex]] = [array[newIndex], array[index]];
        },

        async save() {
            this.saving = true;
            this.errors = [];

            try {
                const response = await fetch('{{ route("admin.resume.editor.save") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        version: this.version,
                        data: this.data,
                        notify_recipients: this.notifyRecipients,
                    }),
                });

                const result = await response.json();

                if (!response.ok) {
                    if (result.errors) {
                        this.errors = Object.values(result.errors).flat();
                    } else {
                        this.errors = [result.message || 'Failed to save'];
                    }
                    return;
                }

                // Success - show message and reload to update UI state
                window.location.reload();

            } catch (error) {
                this.errors = ['Network error: ' + error.message];
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endsection
