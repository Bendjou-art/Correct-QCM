import 'package:flutter/material.dart';

// Couleurs cohérentes avec le web (dashboard.css)
class AppColors {
  static const purple = Color(0xFF6C5DD3);
  static const purpleLight = Color(0xFFF4F2FE);
  static const blue = Color(0xFF3F8CFF);
  static const yellow = Color(0xFFFFB33E);
  static const green = Color.fromARGB(255, 146, 170, 157);
  static const red = Color(0xFFFF754C);
  static const bg = Color(0xFFF7F8FC);
  static const card = Color(0xFFFFFFFF);
  static const text = Color(0xFF1B1D28);
  static const muted = Color(0xFF92929D);
  static const border = Color(0xFFEFEFF4);
}

class Qcm {
  final String id;
  final String titre;
  final String matiere;
  final int nbCopies;
  final String statut;
  final Color couleur;

  Qcm({
    required this.id,
    required this.titre,
    required this.matiere,
    required this.nbCopies,
    required this.statut,
    required this.couleur,
  });

  factory Qcm.fromJson(Map<String, dynamic> json) {
    const couleurs = [
      Color(0xFF2E3A46),
      Color(0xFF1E7A5C),
      Color(0xFF6C5DD3),
      Color(0xFF3F8CFF),
      Color(0xFFE0A94D),
      Color(0xFFD9645A),
    ];
    final index = (json['id']?.hashCode ?? 0).abs() % couleurs.length;

    return Qcm(
      id: json['id'] ?? '',
      titre: json['titre'] ?? '',
      matiere: json['matiere'] ?? '',
      nbCopies: json['nb_copies'] ?? 0,
      statut: json['statut'] ?? 'brouillon',
      couleur: couleurs[index],
    );
  }
}

class HomeScreen extends StatefulWidget {
  final String prenom;
  final String nom;

  const HomeScreen({super.key, required this.prenom, required this.nom});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Qcm> mesQcms = [];
  bool chargement = true;
  String recherche = '';

  @override
  void initState() {
    super.initState();
    chargerQcms();
  }

  Future<void> chargerQcms() async {
    // TODO: remplacer par un vrai appel à l'API PHP
    // final response = await http.get(Uri.parse('$API_BASE_URL/api/qcms/list.php'), ...);
    // final data = jsonDecode(response.body);
    // setState(() { mesQcms = (data['qcms'] as List).map((q) => Qcm.fromJson(q)).toList(); });

    await Future.delayed(const Duration(milliseconds: 500));
    setState(() {
      mesQcms = [
        Qcm(
            id: '1',
            titre: 'Examen Final L3',
            matiere: 'Mathématiques',
            nbCopies: 45,
            statut: 'pret',
            couleur: const Color(0xFF2E3A46)),
        Qcm(
            id: '2',
            titre: 'INFO301',
            matiere: 'Programmation Web',
            nbCopies: 31,
            statut: 'en_correction',
            couleur: const Color(0xFF1E7A5C)),
        Qcm(
            id: '3',
            titre: 'Contrôle continu L1',
            matiere: 'Algorithmique',
            nbCopies: 60,
            statut: 'cloture',
            couleur: AppColors.purple),
      ];
      chargement = false;
    });
  }

  Map<String, dynamic> _statutInfo(String statut) {
    switch (statut) {
      case 'pret':
        return {
          'label': 'Prêt',
          'bg': const Color(0xFFE9FBF2),
          'color': AppColors.green
        };
      case 'en_correction':
        return {
          'label': 'En correction',
          'bg': const Color(0xFFFFF6E7),
          'color': AppColors.yellow
        };
      case 'cloture':
        return {
          'label': 'Clôturé',
          'bg': const Color(0xFFF1F1F5),
          'color': AppColors.muted
        };
      default:
        return {
          'label': 'Brouillon',
          'bg': const Color(0xFFEAF2FF),
          'color': AppColors.blue
        };
    }
  }

  @override
  Widget build(BuildContext context) {
    final initiales =
        '${widget.prenom.isNotEmpty ? widget.prenom[0] : ''}${widget.nom.isNotEmpty ? widget.nom[0] : ''}'
            .toUpperCase();

    final qcmsFiltres = mesQcms.where((q) {
      final query = recherche.toLowerCase();
      return q.titre.toLowerCase().contains(query) ||
          q.matiere.toLowerCase().contains(query);
    }).toList();

    return Scaffold(
      backgroundColor: AppColors.bg,
      drawer: _buildDrawer(context, initiales),
      appBar: AppBar(
        backgroundColor: AppColors.card,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.text),
        title: const Text(
          'Tailor Correct',
          style: TextStyle(
              color: AppColors.text, fontWeight: FontWeight.bold, fontSize: 18),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: CircleAvatar(
              backgroundColor: AppColors.purple,
              radius: 18,
              child: Text(initiales,
                  style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 13)),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.purple,
        onPressed: () {
          Navigator.pushNamed(context, '/session-create');
        },
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: RefreshIndicator(
        onRefresh: chargerQcms,
        child: chargement
            ? const Center(
                child: CircularProgressIndicator(color: AppColors.purple))
            : CustomScrollView(
                slivers: [
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Bienvenue, ${widget.prenom} 👋',
                            style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: AppColors.text),
                          ),
                          const SizedBox(height: 16),
                          _buildSearchBar(),
                          const SizedBox(height: 20),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Mes QCM',
                                  style: TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.text)),
                              TextButton(
                                onPressed: () => Navigator.pushNamed(
                                    context, '/session-create'),
                                child: const Text('+ Créer un QCM',
                                    style: TextStyle(
                                        color: AppColors.purple,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 13)),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  qcmsFiltres.isEmpty
                      ? const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.all(40),
                            child: Center(
                              child: Text('Aucun QCM créé pour l\'instant.',
                                  style: TextStyle(color: AppColors.muted)),
                            ),
                          ),
                        )
                      : SliverPadding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          sliver: SliverList(
                            delegate: SliverChildBuilderDelegate(
                              (context, index) =>
                                  _buildQcmCard(context, qcmsFiltres[index]),
                              childCount: qcmsFiltres.length,
                            ),
                          ),
                        ),
                  const SliverToBoxAdapter(child: SizedBox(height: 80)),
                ],
              ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border),
      ),
      child: TextField(
        onChanged: (value) => setState(() => recherche = value),
        decoration: const InputDecoration(
          hintText: 'Rechercher un QCM, une copie...',
          hintStyle: TextStyle(color: AppColors.muted, fontSize: 13),
          prefixIcon: Icon(Icons.search, color: AppColors.muted, size: 20),
          border: InputBorder.none,
          contentPadding: EdgeInsets.symmetric(vertical: 12),
        ),
      ),
    );
  }

  Widget _buildQcmCard(BuildContext context, Qcm qcm) {
    final statutInfo = _statutInfo(qcm.statut);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          Navigator.pushNamed(context, '/session-detail', arguments: qcm.id);
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: qcm.couleur,
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(12)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    qcm.titre,
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    qcm.matiere,
                    style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.9),
                        fontSize: 13),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('${qcm.nbCopies} copie${qcm.nbCopies > 1 ? 's' : ''}',
                      style: const TextStyle(
                          color: AppColors.muted, fontSize: 13)),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: statutInfo['bg'],
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      statutInfo['label'],
                      style: TextStyle(
                          color: statutInfo['color'],
                          fontWeight: FontWeight.bold,
                          fontSize: 11),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDrawer(BuildContext context, String initiales) {
    return Drawer(
      backgroundColor: AppColors.card,
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.all(20),
              child: Row(
                children: [
                  Text('✂️ ', style: TextStyle(fontSize: 20)),
                  Text('Tailor',
                      style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 18,
                          color: AppColors.text)),
                  Text('Correct',
                      style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 18,
                          color: AppColors.purple)),
                ],
              ),
            ),
            _drawerItem(
                context, Icons.dashboard_outlined, 'Tableau de bord', '/home',
                active: true),
            _drawerItem(context, Icons.folder_outlined, 'Mes QCM', '/sessions'),
            _drawerItem(context, Icons.add_circle_outline, 'Créer un QCM',
                '/session-create'),
            _drawerItem(context, Icons.description_outlined, 'Copies & notes',
                '/copies'),
            _drawerItem(context, Icons.warning_amber_outlined, 'Anomalies',
                '/anomalies'),
            _drawerItem(context, Icons.bar_chart_outlined, 'Statistiques',
                '/statistiques'),
            const Spacer(),
            const Divider(),
            _drawerItem(context, Icons.logout, 'Déconnexion', '/login',
                danger: true),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }

  Widget _drawerItem(
      BuildContext context, IconData icon, String label, String route,
      {bool active = false, bool danger = false}) {
    final color =
        danger ? AppColors.red : (active ? Colors.white : AppColors.muted);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
      child: Material(
        color: active ? AppColors.purple : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        child: ListTile(
          leading: Icon(icon, color: color, size: 20),
          title: Text(label,
              style: TextStyle(
                  color: color, fontWeight: FontWeight.w500, fontSize: 14)),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          onTap: () {
            Navigator.pop(context);
            if (route != '/home') {
              Navigator.pushNamed(context, route);
            }
          },
        ),
      ),
    );
  }
}
