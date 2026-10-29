import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/news_model.dart';
import '../services/api_service.dart';

// advisor_news_screen.dart
// หน้าจอ "จัดการข่าวประชาสัมพันธ์" สำหรับอาจารย์ที่ปรึกษา ถอดแบบจาก advisor_news.php
class AdvisorNewsScreen extends StatefulWidget {
  const AdvisorNewsScreen({super.key});

  @override
  State<AdvisorNewsScreen> createState() => _AdvisorNewsScreenState();
}

class _AdvisorNewsScreenState extends State<AdvisorNewsScreen> {
  bool _isLoading = false;
  List<NewsModel> _newsList = [];

  @override
  void initState() {
    super.initState();
    _loadNews();
  }

  Future<void> _loadNews() async {
    setState(() => _isLoading = true);
    final res = await ApiService.managementGet('get_management_news');
    if (!mounted) return;
    if (res.isSuccess && res.data != null) {
      _newsList = (res.data as List)
          .map((row) => NewsModel.fromJson(Map<String, dynamic>.from(row)))
          .toList();
    }
    setState(() => _isLoading = false);
  }

  void _showAddNewsDialog([NewsModel? news]) {
    final titleController = TextEditingController(text: news?.title);
    final contentController = TextEditingController(text: news?.content);

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          news == null
              ? 'ประกาศข่าวประชาสัมพันธ์ใหม่'
              : 'แก้ไขข่าวประชาสัมพันธ์',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: titleController,
                decoration: const InputDecoration(
                  labelText: 'หัวข้อข่าว *',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: contentController,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'เนื้อหาข่าวสาร...',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
            onPressed: () async {
              final title = titleController.text.trim();
              final content = contentController.text.trim();
              if (title.isEmpty || content.isEmpty) return;
              Navigator.pop(context);
              final response = await ApiService.managementPost(
                news == null ? 'create_news' : 'edit_news',
                {
                  'title': title,
                  'content': content,
                  if (news != null) 'news_id': news.newsId.toString(),
                },
              );
              if (!mounted) return;
              ScaffoldMessenger.of(
                this.context,
              ).showSnackBar(SnackBar(content: Text(response.message)));
              if (response.isSuccess) _loadNews();
            },
            child: const Text('เผยแพร่'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'จัดการข่าวประชาสัมพันธ์',
          style: TextStyle(
            color: AppColors.primaryDark,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(
            Icons.arrow_back_ios_new,
            color: AppColors.primaryDark,
            size: 20,
          ),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showAddNewsDialog(),
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text(
          'เพิ่มข่าวสาร',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _newsList.isEmpty
          ? const Center(
              child: Text(
                'ยังไม่มีข่าวประชาสัมพันธ์',
                style: TextStyle(color: Colors.grey),
              ),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _newsList.length,
              itemBuilder: (context, index) {
                final item = _newsList[index];
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primaryPale,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'ข่าวประชาสัมพันธ์',
                              style: TextStyle(
                                color: AppColors.primaryDark,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          const Spacer(),
                          IconButton(
                            tooltip: 'แก้ไขข่าว',
                            icon: const Icon(Icons.edit_outlined),
                            onPressed: () => _showAddNewsDialog(item),
                          ),
                          IconButton(
                            icon: const Icon(
                              Icons.delete_outline,
                              size: 20,
                              color: Colors.redAccent,
                            ),
                            onPressed: () async {
                              final confirmed = await showDialog<bool>(
                                context: context,
                                builder: (context) => AlertDialog(
                                  title: const Text('ลบข่าวประชาสัมพันธ์'),
                                  content: Text(
                                    'ต้องการลบข่าว "${item.title}" หรือไม่?',
                                  ),
                                  actions: [
                                    TextButton(
                                      onPressed: () =>
                                          Navigator.pop(context, false),
                                      child: const Text('ยกเลิก'),
                                    ),
                                    TextButton(
                                      onPressed: () =>
                                          Navigator.pop(context, true),
                                      child: const Text('ลบ'),
                                    ),
                                  ],
                                ),
                              );
                              if (confirmed != true) return;
                              final response = await ApiService.saveNews(
                                deleteId: item.newsId,
                              );
                              if (!mounted) return;
                              ScaffoldMessenger.of(this.context).showSnackBar(
                                SnackBar(content: Text(response.message)),
                              );
                              if (response.isSuccess) _loadNews();
                            },
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        item.content,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Colors.black87,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
